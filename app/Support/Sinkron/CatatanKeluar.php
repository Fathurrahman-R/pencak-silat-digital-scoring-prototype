<?php

namespace App\Support\Sinkron;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        if (! $this->kepemilikan->milikNodeIni($tabel, $model->getAttributes())) {
            return;
        }

        DB::table('sinkron_keluar')->insert([
            'tabel' => $tabel,
            'baris_id' => (string) $model->getKey(),
            'aksi' => $aksi,
            'dicatat_pada' => now(),
        ]);
    }

    /** Nomor catatan terakhir -- kursor yang diberikan ke peer setelah menarik. */
    public function kursorTerakhir(): int
    {
        return (int) DB::table('sinkron_keluar')->max('id');
    }
}
