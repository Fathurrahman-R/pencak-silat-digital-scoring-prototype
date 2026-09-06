<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun paket berisi perubahan yang belum ditarik sebuah peer.
 *
 * # Kenapa isinya dibaca segar, bukan diambil dari catatan
 *
 * `sinkron_keluar` cuma menyimpan penunjuk: nama tabel dan id barisnya. Isi
 * barisnya dibaca di sini, saat paket disusun, langsung dari tabelnya.
 *
 * Akibatnya sebuah baris yang berubah sepuluh kali sejak penarikan terakhir
 * terkirim SEKALI, dalam keadaan terakhirnya. Nilai yang terbit lalu
 * dibatalkan tiga detik kemudian tidak pernah sampai ke peer dalam keadaan
 * "masih berlaku" -- ia sampai sudah dibatalkan, dan tidak ada urutan yang
 * harus dijaga supaya pembatalannya tidak mendahului penerbitannya.
 *
 * # Kenapa penyaringan kepemilikan diulang di sini
 *
 * CatatanKeluar sudah menolak mencatat baris milik peer lain. Penyaringan
 * kedua di sini bukan mubazir: kepemilikan sebuah baris BISA BERPINDAH.
 * Partai yang dipindahkan sekretariat dari gelanggang A ke gelanggang B
 * membawa serta seluruh nilai dan hukumannya, dan catatan lama yang sudah
 * terlanjur tertulis di A tidak boleh membuat A terus mengirimkan baris yang
 * kini milik B.
 */
class PembungkusPaket
{
    public function __construct(private readonly Kepemilikan $kepemilikan) {}

    /**
     * @return array{
     *     node: string,
     *     peran: string,
     *     kursor: int,
     *     selesai: bool,
     *     baris: list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>,
     * }
     */
    public function bangun(int $sejak, ?int $batas = null): array
    {
        $batas ??= (int) config('sinkron.potongan', 500);

        $catatan = DB::table('sinkron_keluar')
            ->where('id', '>', $sejak)
            ->orderBy('id')
            ->limit($batas)
            ->get(['id', 'tabel', 'baris_id', 'aksi']);

        $kursor = $catatan->isEmpty() ? $sejak : (int) $catatan->last()->id;

        return [
            'node' => $this->kepemilikan->namaNode(),
            'peran' => (string) config('sinkron.peran'),
            'kursor' => $kursor,
            /*
             * "Selesai" berarti potongan ini lebih pendek dari batas, jadi
             * tidak ada lagi yang menunggu. Penarik memutar sampai bendera ini
             * naik, bukan sampai paket kosong -- paket yang kebetulan seluruh
             * isinya tersaring habis tetap membawa kursor yang maju, dan
             * berhenti di situ berarti melewatkan sisanya.
             */
            'selesai' => $catatan->count() < $batas,
            'baris' => $this->susunBaris($catatan),
        ];
    }

    /**
     * @param  Collection<int, object>  $catatan
     * @return list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>
     */
    private function susunBaris($catatan): array
    {
        // Dipadatkan per (tabel, baris_id): yang terakhir menang, karena isinya
        // toh dibaca segar dan keadaan terakhirlah yang benar.
        $terakhir = [];

        foreach ($catatan as $satu) {
            $terakhir[$satu->tabel.'|'.$satu->baris_id] = $satu;
        }

        $hasil = [];

        foreach ($terakhir as $satu) {
            if (! PetaSinkron::disinkronkan($satu->tabel)) {
                continue;
            }

            $baris = DB::table($satu->tabel)->where('id', $satu->baris_id)->first();

            /*
             * Baris yang sudah tidak ada dikirim sebagai penghapusan, apa pun
             * yang tercatat. Catatan bisa menyebut "simpan" untuk baris yang
             * dihapus sesudahnya oleh cascade -- dan cascade tidak menembakkan
             * event model, jadi tidak ada catatan "hapus" yang menyusul.
             */
            if ($baris === null) {
                $hasil[] = [
                    'tabel' => $satu->tabel,
                    'id' => (string) $satu->baris_id,
                    'aksi' => CatatanKeluar::HAPUS,
                ];

                continue;
            }

            $data = (array) $baris;

            if (! $this->kepemilikan->milikNodeIni($satu->tabel, $data)) {
                continue;
            }

            $hasil[] = [
                'tabel' => $satu->tabel,
                'id' => (string) $satu->baris_id,
                'aksi' => CatatanKeluar::SIMPAN,
                'data' => $data,
            ];
        }

        return array_values($hasil);
    }
}
