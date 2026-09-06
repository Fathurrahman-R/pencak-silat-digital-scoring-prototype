<?php

namespace App\Support\Pemantauan;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mencatat berapa lama satu tarikan state berlangsung.
 *
 * # Kenapa disampel, bukan dicatat semuanya
 *
 * Endpoint state ditarik tiap panel yang terbuka, tiap ada siaran, ditambah
 * sekali tiap dua puluh detik selama babak berjalan. Enam panel di satu
 * gelanggang berarti puluhan tarikan per menit. Mencatat semuanya berarti
 * menambah satu penulisan berkas ke jalur yang paling sering dilewati -- alat
 * ukur yang ikut memperburuk angka yang sedang diukurnya.
 *
 * Satu dari lima sudah cukup: yang dicari persentil kasar, bukan riwayat
 * lengkap.
 *
 * # Kenapa berkas, bukan cache atau tabel
 *
 * Cache butuh kunci saat ditulis bersamaan, dan penulisnya adalah delapan
 * proses php-cgi yang berjalan berbarengan. Tabel berarti menambah penulisan
 * ke basis data yang justru sedang diselidiki kesehatannya. Berkas biasa yang
 * ditambah di ujung tidak butuh keduanya, dan kehilangan satu-dua baris saat
 * dua proses menulis bersamaan tidak mengubah persentilnya.
 *
 * Kegagalan apa pun di sini ditelan diam-diam. Pemantauan yang menjatuhkan
 * permintaan yang sedang dipantaunya lebih buruk daripada pemantauan yang
 * tidak ada.
 */
class CatatWaktuTarikan
{
    public function catat(float $milidetik): void
    {
        $satuDari = max(1, (int) config('pemantauan.sampel.satu_dari', 5));

        if (random_int(1, $satuDari) !== 1) {
            return;
        }

        try {
            $berkas = (string) config('pemantauan.sampel.berkas');
            $disk = Storage::disk('local');

            $isi = $disk->exists($berkas) ? $disk->get($berkas) : '';
            $baris = array_filter(explode("\n", $isi));
            $baris[] = (string) (int) round($milidetik);

            /*
             * Dipotong dari depan supaya berkasnya tidak tumbuh selamanya, dan
             * supaya persentilnya menggambarkan keadaan SEKARANG. Sampel dari
             * pagi hari tidak menjelaskan apa-apa tentang panel yang melambat
             * sore ini.
             */
            $simpan = max(50, (int) config('pemantauan.sampel.simpan_baris', 500));

            $disk->put($berkas, implode("\n", array_slice($baris, -$simpan)));
        } catch (Throwable) {
            // Sengaja diam: lihat catatan di kepala kelas.
        }
    }
}
