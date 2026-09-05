<?php

namespace App\Support\Sinkron;

use Illuminate\Support\Facades\DB;

/**
 * Menerapkan paket yang ditarik dari peer.
 *
 * # Idempoten, dan itu yang membuat penarikan boleh terputus
 *
 * Tiap baris diterapkan sebagai upsert: ada, ditimpa; belum ada, disisipkan.
 * Menerapkan paket yang sama dua kali menghasilkan keadaan yang sama persis.
 * Karena itu penarikan yang putus di tengah -- laptop tertutup, kabel
 * tersenggol -- cukup diulang dari kursor terakhir yang tersimpan, tanpa ada
 * yang perlu tahu berapa banyak yang sempat masuk.
 *
 * # Urutan penerapan bukan urutan kedatangan
 *
 * Baris diurutkan ulang mengikuti PetaSinkron::urutanTerapkan() sebelum
 * ditulis. Foreign key menolak baris yang menunjuk sesuatu yang belum ada,
 * dan paket yang sah pun akan ditolak di tengah kalau nilai tiba sebelum
 * partainya.
 *
 * # Kenapa menulis lewat query builder, bukan model
 *
 * Menulis lewat model akan menembakkan observer, dan SinkronObserver akan
 * mencatat baris yang BARU SAJA DITERIMA sebagai perubahan lokal -- lalu
 * mengirimkannya kembali ke pengirimnya pada penarikan berikutnya. Query
 * builder melewati observer, memutus lingkaran itu di tempatnya.
 *
 * Konsekuensinya observer lain ikut terlewat, termasuk yang membatalkan
 * snapshot skor. Itu ditangani sendiri di akhir: partai yang tersentuh
 * dikumpulkan, lalu snapshotnya dibatalkan sekaligus.
 */
class PenerapPaket
{
    public function __construct(private readonly Kepemilikan $kepemilikan) {}

    /**
     * @param  array{baris?: list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>}  $paket
     * @return array{diterapkan: int, dihapus: int, ditolak: int, dilewati: int}
     */
    public function terapkan(array $paket): array
    {
        $baris = $paket['baris'] ?? [];

        $ringkasan = ['diterapkan' => 0, 'dihapus' => 0, 'ditolak' => 0, 'dilewati' => 0];
        $partaiTersentuh = [];

        DB::transaction(function () use ($baris, &$ringkasan, &$partaiTersentuh) {
            foreach ($this->urutkan($baris) as $satu) {
                $tabel = $satu['tabel'];

                if (! PetaSinkron::disinkronkan($tabel)) {
                    $ringkasan['dilewati']++;

                    continue;
                }

                $data = $satu['data'] ?? [];

                /*
                 * Penolakan baris milik sendiri -- pemutus lingkaran yang
                 * kedua, dan yang paling menentukan.
                 *
                 * Peer boleh saja mengirimkan kembali baris yang dulu ia
                 * terima dari sini. Menerimanya berarti menimpa catatan asli
                 * dengan salinan yang sudah tertinggal beberapa penarikan --
                 * nilai yang sudah dibatalkan hidup lagi, tepat pada partai
                 * yang sedang disengketakan.
                 */
                if ($satu['aksi'] === CatatanKeluar::HAPUS) {
                    /*
                     * Kepemilikan baris yang akan dihapus dibaca dari salinan
                     * LOKAL, bukan dari paket -- paket penghapusan tidak
                     * membawa isi barisnya, dan tanpa isi tidak ada cara tahu
                     * gelanggang mana pemiliknya. Baris yang sudah tidak ada
                     * di sini dianggap selesai.
                     */
                    $lokal = DB::table($tabel)->where('id', $satu['id'])->first();

                    if ($lokal === null) {
                        $ringkasan['dilewati']++;

                        continue;
                    }

                    if ($this->kepemilikan->milikNodeIni($tabel, (array) $lokal)) {
                        $ringkasan['ditolak']++;

                        continue;
                    }

                    DB::table($tabel)->where('id', $satu['id'])->delete();
                    $ringkasan['dihapus']++;

                    continue;
                }

                if ($data === [] || $this->kepemilikan->milikNodeIni($tabel, $data)) {
                    $ringkasan['ditolak']++;

                    continue;
                }

                DB::table($tabel)->upsert([$data], ['id'], array_keys($data));
                $ringkasan['diterapkan']++;

                if (isset($data['match_id'])) {
                    $partaiTersentuh[(string) $data['match_id']] = true;
                }
            }
        });

        $this->batalkanSnapshot(array_keys($partaiTersentuh));

        return $ringkasan;
    }

    /**
     * Mengurutkan baris mengikuti urutan tabel yang aman terhadap foreign key.
     *
     * @param  list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>  $baris
     * @return list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>
     */
    private function urutkan(array $baris): array
    {
        $urutan = array_flip(PetaSinkron::urutanTerapkan());

        usort($baris, static fn ($a, $b) => ($urutan[$a['tabel']] ?? PHP_INT_MAX) <=> ($urutan[$b['tabel']] ?? PHP_INT_MAX));

        /*
         * Penghapusan dikerjakan dalam urutan terbalik, setelah penyisipan.
         * Menghapus induk sebelum anaknya melanggar foreign key yang sama,
         * cuma dari arah sebaliknya.
         */
        $simpan = array_values(array_filter($baris, static fn ($b) => $b['aksi'] !== CatatanKeluar::HAPUS));
        $hapus = array_reverse(array_values(array_filter($baris, static fn ($b) => $b['aksi'] === CatatanKeluar::HAPUS)));

        return array_merge($simpan, $hapus);
    }

    /**
     * Membatalkan snapshot skor partai yang barusan menerima perubahan.
     *
     * Penulisan lewat query builder tidak menembakkan SnapshotSkorObserver,
     * jadi tanpa langkah ini panel akan terus menampilkan angka yang dihitung
     * sebelum nilai dari gelanggang lain masuk -- dan angka itu tidak akan
     * pernah menyusul sendiri, karena tidak ada yang menandainya basi.
     *
     * @param  list<string>  $matchId
     */
    private function batalkanSnapshot(array $matchId): void
    {
        if ($matchId === []) {
            return;
        }

        DB::table('matches')->whereIn('id', $matchId)->update(['snapshot_pada' => null]);
    }
}
