<?php

namespace App\Support\Ekspor;

/**
 * Penulis CSV yang menetralkan sel berbentuk rumus.
 *
 * Excel dan LibreOffice mengeksekusi sel yang diawali `=`, `+`, `-`, `@`,
 * tab, atau carriage return begitu berkasnya dibuka. Tanda kutip ganda CSV
 * tidak menolong sama sekali: kutip melindungi pemisah kolom, bukan mencegah
 * selnya dibaca sebagai rumus.
 *
 * Ini penting justru karena isi ekspor datang dari peserta. Nama kontingen
 * dan nama atlet diisi official kontingen sendiri, jadi setiap ekspor adalah
 * jalur langsung dari input peserta ke komputer panitia. Rumus seperti
 * `=HYPERLINK("http://…"&A1)` berjalan di sana dan bisa menarik isi sel lain
 * ke server luar.
 *
 * Dipakai satu kelas ini di seluruh ekspor, bukan penambalan per berkas:
 * ekspor bertambah dari waktu ke waktu, dan satu yang lupa dijaga sudah
 * cukup untuk membuka kembali celahnya.
 */
class TulisCsv
{
    /**
     * Tanda yang membuat Excel membaca sel sebagai rumus, bukan teks.
     *
     * Tab dan carriage return ikut karena keduanya bisa dipakai menggeser
     * isi sel sehingga tanda rumus yang sebenarnya tidak lagi berada di
     * posisi pertama saat diperiksa.
     */
    private const TANDA_RUMUS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Menetralkan seluruh sel dalam satu baris.
     *
     * Kutip tunggal di depan adalah cara Excel sendiri menyatakan "sel ini
     * teks": tanda kutipnya tidak ikut tampil di layar, dan nilai aslinya
     * tetap terbaca utuh oleh manusia maupun oleh pembaca CSV lain.
     *
     * @param  array<int, mixed>  $baris
     * @return array<int, mixed>
     */
    public static function aman(array $baris): array
    {
        return array_map(static function (mixed $sel): mixed {
            if (! is_string($sel) || $sel === '') {
                return $sel;
            }

            return in_array($sel[0], self::TANDA_RUMUS, true) ? "'".$sel : $sel;
        }, $baris);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $baris
     */
    public static function tulis($handle, array $baris): void
    {
        fputcsv($handle, self::aman($baris));
    }
}
