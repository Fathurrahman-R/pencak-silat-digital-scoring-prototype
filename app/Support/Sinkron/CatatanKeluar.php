<?php

namespace App\Support\Sinkron;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat baris apa saja yang berubah, supaya peer bisa menarik yang baru
 * saja tanpa memeriksa seluruh basis data.
 *
 * # Kenapa penunjuk, bukan salinan
 *
 * Yang dicatat cuma nama tabel dan id barisnya. Isinya dibaca segar saat
 * paket disusun. Akibatnya dua hal yang keduanya penting:
 *
 * Satu, paket selalu membawa keadaan TERKINI. Nilai yang terbit lalu
 * dibatalkan tiga detik kemudian terkirim sekali, sudah dalam keadaan
 * dibatalkan -- bukan dua kali dengan urutan yang harus dijaga.
 *
 * Dua, penerapannya idempoten. Menerapkan paket yang sama dua kali
 * menghasilkan keadaan yang sama, jadi penarikan yang terputus di tengah
 * cukup diulang dari kursor terakhir yang tersimpan, tanpa perlu tahu berapa
 * banyak yang sempat masuk.
 *
 * # Kenapa tidak memadatkan catatan lama
 *
 * Baris yang berubah sepuluh kali meninggalkan sepuluh catatan, dan
 * kesepuluhnya menunjuk ke isi yang sama. Menghapus yang lama terlihat rapi,
 * tapi kursor peer menunjuk angka tertentu di daftar ini: menghapus catatan
 * di bawah kursor sebuah peer yang sedang menarik berarti membuat peer itu
 * melompati perubahan tanpa ada yang tahu. Catatan dibiarkan tumbuh, dan
 * dipangkas hanya saat tidak ada penarikan berjalan.
 */
class CatatanKeluar
{
    public const SIMPAN = 'simpan';

    public const HAPUS = 'hapus';

    /*
     * Penanda "node ini sudah menyemai catatannya", disimpan di tabel kursor
     * sebagai peer bernama khusus. Bukan tabel baru: yang dicatat memang satu
     * angka dan satu waktu, persis bentuk baris kursor, dan halaman sinkron
     * hanya menggambar peer yang terdaftar di konfigurasi sehingga baris ini
     * tidak pernah ikut tampil.
     */
    public const PENANDA_SEMAI = '__semai__';

    public function __construct(private readonly Kepemilikan $kepemilikan) {}

    /**
     * Mencatat satu perubahan, kalau memang layak dicatat.
     *
     * Yang tidak dicatat: tabel yang tidak ikut sinkron (judge_inputs yang
     * mengalir lewat arsip, dan seluruh tabel infrastruktur), serta baris yang
     * bukan milik node ini. Yang kedua itu yang memutus lingkaran -- tanpa
     * dia, baris yang baru saja DITERIMA dari peer langsung tercatat sebagai
     * perubahan lokal dan dikirim balik ke pengirimnya.
     */
    public function catat(Model $model, string $aksi): void
    {
        $tabel = $model->getTable();

        if (! PetaSinkron::disinkronkan($tabel)) {
            return;
        }

        if (! $this->kepemilikan->bolehMengirim($tabel, $model->getAttributes())) {
            return;
        }

        DB::table('sinkron_keluar')->insert([
            'tabel' => $tabel,
            'baris_id' => PetaSinkron::penandaBaris($tabel, $model->getAttributes()),
            'aksi' => $aksi,
            'dicatat_pada' => now(),
        ]);
    }

    /**
     * Mencatat satu baris lewat penanda kuncinya, tanpa lewat model.
     *
     * Dipakai tabel pivot murni yang tidak punya model Eloquent sama sekali,
     * jadi tidak pernah menembakkan observer -- lihat CatatPivotPeran.
     * Kepemilikannya tetap diperiksa: node gelanggang tidak mencatat pivot
     * peran, karena peran bergolongan GLOBAL dan yang menulisnya node global.
     */
    public function catatPenanda(string $tabel, string $penanda, string $aksi = self::SIMPAN): void
    {
        if (! PetaSinkron::disinkronkan($tabel)) {
            return;
        }

        if (! $this->kepemilikan->bolehMengirim($tabel, PetaSinkron::klausaKunci($tabel, $penanda))) {
            return;
        }

        DB::table('sinkron_keluar')->insert([
            'tabel' => $tabel,
            'baris_id' => $penanda,
            'aksi' => $aksi,
            'dicatat_pada' => now(),
        ]);
    }

    /** Nomor catatan terakhir -- kursor yang diberikan ke peer setelah menarik. */
    public function kursorTerakhir(): int
    {
        return (int) DB::table('sinkron_keluar')->max('id');
    }

    /**
     * Menyemai catatan dari KEADAAN SEKARANG, sekali, untuk node yang belum
     * pernah menyemai.
     *
     * # Kenapa ini harus ada
     *
     * Catatan ini lahir dari observer: ia hanya berisi baris yang BERUBAH
     * sesudah observernya terpasang. Seluruh data yang sudah ada sebelum itu
     * -- dan data yang lahir lewat sisipan massal yang melewati Eloquent --
     * tidak pernah tercatat sama sekali.
     *
     * Akibatnya terukur, dan uji kotak hitam 11 September 2026 memotretnya:
     * node global punya 64 `jurus_events` di basis datanya dan NOL di
     * catatannya. Node gelanggang yang baru dipasang menarik dari nol, dan
     * yang ia terima adalah penampilan Jurus tanpa nomor Jurus-nya, akun tanpa
     * peran, partai tanpa bagan. Satu pelanggaran foreign key menghentikan
     * seluruh penarikan di potongan pertama, dan mesin itu tidak pernah bisa
     * dipasang -- pesannya pun tidak menyebut sebabnya.
     *
     * Yang disemai cuma baris MILIK node ini. Node global menyemai seluruh
     * data kejuaraan; node gelanggang menyemai hasil pertandingan
     * gelanggangnya sendiri, dan tidak mengklaim apa pun milik tetangganya.
     *
     * @return int jumlah baris yang disemai
     */
    public function semai(): int
    {
        $jumlah = 0;

        /*
         * Urutan mengikuti urutan penerapan: induk lebih dulu. Paket memang
         * diurutkan ulang di sisi penerima, tapi catatan yang urut sejak awal
         * membuat potongan pertama pun berdiri sendiri -- penerima tidak harus
         * menunggu potongan kelima supaya potongan pertamanya sah.
         */
        foreach (PetaSinkron::urutanTerapkan() as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            $kunci = PetaSinkron::kunci($tabel);

            DB::table($tabel)->orderBy($kunci[0])->chunk(500, function ($baris) use ($tabel, &$jumlah) {
                $catatan = [];

                foreach ($baris as $satu) {
                    if (! $this->kepemilikan->bolehMengirim($tabel, (array) $satu)) {
                        continue;
                    }

                    $catatan[] = [
                        'tabel' => $tabel,
                        'baris_id' => PetaSinkron::penandaBaris($tabel, (array) $satu),
                        'aksi' => self::SIMPAN,
                        'dicatat_pada' => now(),
                    ];
                }

                if ($catatan !== []) {
                    DB::table('sinkron_keluar')->insert($catatan);
                    $jumlah += count($catatan);
                }
            });
        }

        DB::table('sinkron_kursor')->upsert([[
            'peer' => self::PENANDA_SEMAI,
            'kursor_terakhir' => $this->kursorTerakhir(),
            'ditarik_pada' => now(),
            'baris_diterapkan' => $jumlah,
            'galat_terakhir' => null,
        ]], ['peer'], ['kursor_terakhir', 'ditarik_pada', 'baris_diterapkan']);

        return $jumlah;
    }

    /** Apakah node ini sudah pernah menyemai catatannya. */
    public function sudahDisemai(): bool
    {
        return DB::table('sinkron_kursor')->where('peer', self::PENANDA_SEMAI)->exists();
    }
}
