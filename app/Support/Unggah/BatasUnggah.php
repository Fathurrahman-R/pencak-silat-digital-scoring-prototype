<?php

namespace App\Support\Unggah;

/**
 * Batas ukuran unggahan yang benar-benar berlaku.
 *
 * Aturan `max:4096` di Laravel tidak ada artinya kalau `upload_max_filesize`
 * PHP hanya 2M: berkas 3 MB dibuang PHP sebelum Laravel sempat memeriksanya,
 * dan yang tersisa hanya pesan gagal generik tanpa sebab. Bendahara yang
 * memotret struk transfer dengan kamera HP akan gagal berulang kali tanpa
 * tahu kenapa -- foto sebesar itu justru ukuran yang biasa.
 *
 * Jadi batasnya dihitung, bukan ditulis dua kali di tempat berbeda: yang
 * berlaku selalu yang terkecil di antara batas PHP dan batas yang diinginkan
 * aplikasi. Angkanya lalu ikut disebut di pesan galat, supaya orang tahu
 * harus mengecilkan sampai berapa.
 */
class BatasUnggah
{
    /**
     * Batas yang berlaku, dalam kilobyte.
     *
     * @param  int  $diinginkan  batas yang dikehendaki aplikasi, kilobyte
     * @param  int|null  $batasPhp  kilobyte; diambil dari php.ini bila null
     */
    public static function kilobyte(int $diinginkan, ?int $batasPhp = null): int
    {
        $php = $batasPhp ?? min(
            self::keKilobyte(ini_get('upload_max_filesize') ?: '0'),
            self::keKilobyte(ini_get('post_max_size') ?: '0'),
        );

        // Nol berarti PHP tidak membatasi; yang diinginkan aplikasi berlaku.
        return $php > 0 ? min($diinginkan, $php) : $diinginkan;
    }

    /** Notasi php.ini (`2M`, `512K`, `1G`, atau byte polos) jadi kilobyte. */
    public static function keKilobyte(string $notasi): int
    {
        $notasi = trim($notasi);

        if ($notasi === '') {
            return 0;
        }

        $angka = (int) $notasi;
        $satuan = strtoupper(substr($notasi, -1));

        return match ($satuan) {
            'G' => $angka * 1024 * 1024,
            'M' => $angka * 1024,
            'K' => $angka,
            default => intdiv($angka, 1024),
        };
    }

    /** Angka kilobyte jadi label megabyte yang wajar dibaca orang. */
    public static function label(int $kilobyte): string
    {
        $mb = $kilobyte / 1024;

        return rtrim(rtrim(number_format($mb, 1, ',', '.'), '0'), ',').' MB';
    }
}
