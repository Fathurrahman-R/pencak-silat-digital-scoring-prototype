<?php

namespace App\Support\Sinkron;

use App\Models\JurusBattle;
use App\Models\SilatMatch;
use App\Support\Bagan\Contracts\Terbagankan;
use App\Support\Bagan\PromosiPemenang;
use Illuminate\Database\Eloquent\Model;
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

        /** @var array<string, list<int|string>> $pemenangTiba */
        $pemenangTiba = [];

        /** @var list<array{string, string, string}> $diteruskan */
        $diteruskan = [];

        /** @var list<array{string, array<string, mixed>}> $penghapusan */
        $penghapusan = [];

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
            DB::transaction(function () use ($baris, $mysql, &$ringkasan, &$partaiTersentuh, &$pemenangTiba, &$diteruskan, &$penghapusan) {
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

                        /*
                         * Penghapusan baris penghubung tetap diterima walau
                         * barisnya milik gelanggang ini.
                         *
                         * Partai disisipkan node global dan diperbarui
                         * gelanggangnya; yang MENGHAPUSNYA cuma node global,
                         * saat bagan dibongkar dan disusun ulang -- alur yang
                         * memang ada, lewat tombol buka kunci. Menolaknya
                         * berarti partai yang sudah tidak ada di node global
                         * tetap berdiri di antrean gelanggang, dan pengendali
                         * menayangkan partai hantu: pointer tayangnya
                         * menunjuk ke sana, dan partai sungguhan berikutnya
                         * ditolak karena "masih ada partai berjalan".
                         * Terlihat begitu di node B, 12 September 2026.
                         *
                         * Yang dijaga kepemilikan adalah PEMBARUAN, supaya
                         * salinan basi tidak menimpa hasil pertandingan.
                         * Penghapusan tidak punya salinan basi: barisnya
                         * memang sudah tidak ada di hulunya.
                         */
                        $penghubung = in_array($tabel, PetaSinkron::PENGHUBUNG, true);

                        if (! $penghubung && $this->kepemilikan->milikNodeIni($tabel, (array) $lokal)) {
                            $ringkasan['ditolak']++;

                            continue;
                        }

                        /*
                         * Dikumpulkan, tidak langsung dihapus: penghapusan
                         * dikerjakan sesudah pemeriksaan foreign key dinyalakan
                         * lagi, supaya ia merambat ke barisnya sendiri.
                         */
                        $penghapusan[] = [$tabel, $klausa];
                        $ringkasan['dihapus']++;
                        $diteruskan[] = [$tabel, (string) $satu['id'], CatatanKeluar::HAPUS];

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

                    if (isset(self::BERBAGAN[$tabel]) && ($data['winner_registration_id'] ?? null) !== null) {
                        $pemenangTiba[$tabel][] = $data['id'];
                    }

                    $diteruskan[] = [$tabel, (string) $satu['id'], CatatanKeluar::SIMPAN];
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

                /*
                 * Penghapusan dikerjakan paling akhir, dengan pemeriksaan
                 * foreign key MENYALA lagi.
                 *
                 * Pemeriksaan yang mati membuat penghapusan berhenti di baris
                 * yang disebut paket: baris turunannya tertinggal menunjuk
                 * induk yang sudah tidak ada. Pendaftaran yang dibatalkan di
                 * node global meninggalkan pivot atletnya di gelanggang, dan
                 * partai yang dibongkar meninggalkan nilai dan hukumannya --
                 * tidak terlihat sampai ada yang menghitung rekap.
                 *
                 * Skema sudah menuliskan perambatannya lewat `cascadeOnDelete`;
                 * yang perlu dilakukan cuma membiarkan basis data menjalankan
                 * apa yang sudah tertulis di sana.
                 */
                if ($mysql) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }

                foreach ($penghapusan as [$tabel, $klausa]) {
                    DB::table($tabel)->where($klausa)->delete();
                }
            });
        } finally {
            if ($mysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $this->batalkanSnapshot(array_keys($partaiTersentuh));
        $this->teruskan($diteruskan);
        $this->naikkanPemenangYangTiba($pemenangTiba);

        return $ringkasan;
    }

    /**
     * Node global meneruskan keadaan partai yang ia terima dari gelanggang.
     *
     * Dokumen menganjurkan tiap laptop gelanggang cukup mengenal node global.
     * Di topologi itu, hasil partai babak satu di gelanggang A hanya bisa
     * sampai ke gelanggang B -- yang menjadwalkan babak duanya -- lewat node
     * global. Tapi penerapan menulis lewat query builder, jadi baris yang
     * diterima tidak pernah tercatat di `sinkron_keluar` node global, dan B
     * menunggu selamanya: KesiapanHulu menolak menayangkan partainya karena
     * hulunya tidak pernah disahkan di sini.
     *
     * Hanya node global, dan hanya tabel penghubung. Nilai dan hukuman milik
     * gelanggang tidak dibutuhkan gelanggang lain, dan node gelanggang yang
     * meneruskan kiriman node global cuma memantulkannya kembali.
     *
     * Pemiliknya sendiri akan menerima kembali barisnya dan menolaknya -- itu
     * pemutus lingkaran yang bekerja sebagaimana mestinya, bukan kegagalan.
     *
     * @param  list<array{string, string, string}>  $diteruskan
     */
    private function teruskan(array $diteruskan): void
    {
        if (! $this->kepemilikan->nodeGlobal()) {
            return;
        }

        $catatan = new CatatanKeluar($this->kepemilikan);

        foreach ($diteruskan as [$tabel, $penanda, $aksi]) {
            if (in_array($tabel, PetaSinkron::PENGHUBUNG, true)) {
                $catatan->catatPenanda($tabel, $penanda, $aksi);
            }
        }
    }

    /**
     * Tabel bagan yang pemenangnya naik ke partai berikutnya.
     *
     * @var array<string, class-string<Terbagankan&Model>>
     */
    private const BERBAGAN = [
        'matches' => SilatMatch::class,
        'jurus_battles' => JurusBattle::class,
    ];

    /**
     * Menaikkan pemenang yang hasilnya baru tiba dari gelanggang lain.
     *
     * Bagan tidak berhenti di batas gelanggang: partai babak satu di A,
     * partai babak duanya di B. Gelanggang A menaikkan pemenangnya di
     * basis datanya sendiri, tapi partai babak dua itu milik B -- A tidak
     * mencatatnya untuk dikirim, dan B menolaknya kalau pun terkirim. Yang
     * sampai ke B cuma hasil partai babak satu.
     *
     * Sebelum ini tidak ada yang menurunkan siapa yang naik dari hasil itu.
     * Sudut partai babak dua kosong selamanya, sementara KesiapanHulu melihat
     * hulu yang sudah disahkan dan menyatakan partainya siap ditayangkan.
     *
     * Letak tujuan dihitung dari aritmetika bagan yang sama dengan
     * PromosiPemenang, jadi tiap node sampai ke jawaban yang sama tanpa perlu
     * saling mengirim. Yang menulis cuma PEMILIK partai tujuan: dialah yang
     * mengirimkan keadaan partai itu ke semua node lain. Lewat model, supaya
     * SinkronObserver mencatatnya.
     *
     * Sudut yang sudah benar tidak disentuh. `update()` pada model yang tidak
     * berubah tetap menembakkan `saved`, dan tiap penarikan akan menerbitkan
     * satu catatan keluar baru yang dikirim berkeliling tanpa isi.
     *
     * @param  array<string, list<int|string>>  $pemenangTiba
     */
    private function naikkanPemenangYangTiba(array $pemenangTiba): void
    {
        $promosi = new PromosiPemenang;

        foreach ($pemenangTiba as $tabel => $id) {
            $model = self::BERBAGAN[$tabel];

            foreach ($model::query()->whereKey($id)->whereNotNull('winner_registration_id')->get() as $hulu) {
                $hilir = $hulu->sesamaBagan()
                    ->where('round', $hulu->round + 1)
                    ->where('position', $hulu->posisiBerikutnya())
                    ->first();

                if ($hilir === null || ! $this->kepemilikan->milikNodeIni($tabel, $hilir->getAttributes())) {
                    continue;
                }

                $sudut = $hulu->sudutBerikutnya().'_registration_id';

                if ((int) $hilir->{$sudut} === (int) $hulu->winner_registration_id) {
                    continue;
                }

                $promosi($hulu);
            }
        }
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
