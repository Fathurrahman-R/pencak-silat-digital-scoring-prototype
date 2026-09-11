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

        /*
         * Pemeriksaan foreign key ditangguhkan selama satu potongan.
         *
         * Urutan di dalam potongan sudah dijaga, tapi penarikan berjalan
         * POTONGAN DEMI POTONGAN dan induk sebuah baris bisa berada di
         * potongan yang lain. Catatan lama yang terbit sebelum penyemaian
         * berdiri di nomor yang lebih kecil, jadi potongan pertama sebuah node
         * baru berisi penampilan Jurus sementara nomor Jurus-nya menunggu di
         * potongan ketujuh -- dan satu pelanggaran menghentikan seluruh
         * penarikan, persis yang terjadi 11 September 2026.
         *
         * Yang ditangguhkan cuma pemeriksaannya, bukan datanya: begitu seluruh
         * potongan masuk, tidak ada baris yatim yang tersisa. Ditangguhkan per
         * transaksi, dan dikembalikan apa pun yang terjadi -- koneksi ini juga
         * melayani permintaan lain sesudahnya.
         */
        $mysql = DB::connection()->getDriverName() === 'mysql';

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::transaction(function () use ($baris, &$ringkasan, &$partaiTersentuh) {
                /** @var array<string, list<array<string, mixed>>> $kumpulan */
                $kumpulan = [];

                $this->muatKunciYangAda($baris);

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
                        $klausa = PetaSinkron::klausaKunci($tabel, (string) $satu['id']);
                        $lokal = $klausa === [] ? null : DB::table($tabel)->where($klausa)->first();

                        if ($lokal === null) {
                            $ringkasan['dilewati']++;

                            continue;
                        }

                        if ($this->kepemilikan->milikNodeIni($tabel, (array) $lokal)) {
                            $ringkasan['ditolak']++;

                            continue;
                        }

                        DB::table($tabel)->where($klausa)->delete();
                        $ringkasan['dihapus']++;

                        continue;
                    }

                    if ($data === []) {
                        $ringkasan['ditolak']++;

                        continue;
                    }

                    /*
                     * Baris milik sendiri ditolak -- kecuali belum ada di sini.
                     *
                     * Penolakan ini memutus lingkaran: peer bisa saja
                     * mengirimkan kembali baris yang dulu ia terima dari sini,
                     * dan menerimanya berarti menimpa catatan asli dengan
                     * salinan yang tertinggal beberapa penarikan.
                     *
                     * Tapi baris yang BELUM ADA di sini tidak menimpa apa pun.
                     * Node gelanggang yang baru dipasang memiliki -- menurut
                     * aturan kepemilikan -- seluruh partai gelanggangnya, dan
                     * tidak punya satu pun di basis datanya. Menolaknya berarti
                     * menolak satu-satunya kiriman yang bisa memberinya jadwal
                     * sendiri.
                     */
                    /*
                     * "Sudah ada di sini?" ditanyakan SEKALI PER TABEL, bukan
                     * sekali per baris.
                     *
                     * Lima ratus baris berarti lima ratus `exists()` -- dan
                     * pemasangan satu laptop gelanggang jadi sepuluh menit,
                     * terukur begitu saat menguji pemasangan dari nol. Daftar
                     * kunci yang sudah ada dibaca sekali lalu disimpan selama
                     * potongan ini.
                     */
                    $sudahAda = isset($this->kunciAda[$tabel])
                        ? isset($this->kunciAda[$tabel][(string) $satu['id']])
                        : $this->adaSatuPerSatu($tabel, (string) $satu['id']);

                    if ($sudahAda && $this->kepemilikan->milikNodeIni($tabel, $data)) {
                        $ringkasan['ditolak']++;

                        continue;
                    }

                    /*
                     * Dikumpulkan per tabel, bukan ditulis satu per satu.
                     *
                     * Satu potongan berisi lima ratus baris, dan lima ratus
                     * upsert terpisah membuat pemasangan satu laptop gelanggang
                     * memakan belasan menit -- terukur begitu saat menguji
                     * pemasangan dari nol. Susunan kolomnya seragam karena
                     * isinya dibaca dari tabel yang sama di sisi pengirim.
                     */
                    $kumpulan[$tabel][] = $data;
                    $ringkasan['diterapkan']++;

                    if (isset($data['match_id'])) {
                        $partaiTersentuh[(string) $data['match_id']] = true;
                    }
                }

                foreach ($kumpulan as $tabel => $baris) {
                    foreach (array_chunk($baris, 200) as $sepotong) {
                        DB::table($tabel)->upsert(
                            $sepotong,
                            PetaSinkron::kunci($tabel),
                            array_keys($sepotong[0]),
                        );
                    }
                }
            });
        } finally {
            if ($mysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $this->batalkanSnapshot(array_keys($partaiTersentuh));

        return $ringkasan;
    }

    /**
     * Kunci yang sudah ada di mesin ini, per tabel, untuk satu potongan.
     *
     * @var array<string, array<string, true>>
     */
    private array $kunciAda = [];

    /**
     * Membaca sekali, untuk seluruh tabel yang disebut potongan ini, kunci
     * mana saja yang sudah ada di sini.
     *
     * @param  list<array{tabel: string, id: string, aksi: string, data?: array<string, mixed>}>  $baris
     */
    private function muatKunciYangAda(array $baris): void
    {
        $this->kunciAda = [];

        $perTabel = [];

        foreach ($baris as $satu) {
            $perTabel[$satu['tabel']][] = (string) $satu['id'];
        }

        foreach ($perTabel as $tabel => $penanda) {
            if (! PetaSinkron::disinkronkan($tabel)) {
                continue;
            }

            $kunci = PetaSinkron::kunci($tabel);

            /*
             * Kunci tunggal ditanyakan sekaligus lewat `whereIn`. Kunci
             * gabungan -- tiga tabel pivot peran -- dibaca seluruhnya: isinya
             * sepuluhan sampai ratusan baris, jauh lebih murah daripada satu
             * kueri per baris, dan menyusun `whereIn` bertingkat untuk tiga
             * kolom tidak menghasilkan apa pun yang lebih cepat.
             */
            if ($kunci === ['id']) {
                $ada = DB::table($tabel)->whereIn('id', $penanda)->pluck('id');

                $this->kunciAda[$tabel] = array_fill_keys($ada->map(fn ($satu) => (string) $satu)->all(), true);

                continue;
            }

            $ada = DB::table($tabel)->get()
                ->map(fn ($satu) => PetaSinkron::penandaBaris($tabel, (array) $satu))
                ->all();

            $this->kunciAda[$tabel] = array_fill_keys($ada, true);
        }
    }

    /** Jalan mundur, untuk tabel yang entah kenapa tidak sempat dimuat. */
    private function adaSatuPerSatu(string $tabel, string $penanda): bool
    {
        $klausa = PetaSinkron::klausaKunci($tabel, $penanda);

        return $klausa !== [] && DB::table($tabel)->where($klausa)->exists();
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
