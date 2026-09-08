<?php

namespace App\Enums;

/**
 * Cara satu bagan Tanding disusun dari daftar peserta.
 *
 * Keduanya sama-sama sistem gugur -- yang kalah berhenti, yang menang naik --
 * dan keduanya menghabiskan partai sebanyak peserta dikurangi satu. Yang
 * berbeda hanya bentuk babak pertamanya.
 */
enum ModeBagan: string
{
    /**
     * Ukuran bagan dibulatkan ke pangkat dua terdekat.
     *
     * Tempat yang tersisa jadi bye, dan byenya disebar susunan unggulan baku
     * (lihat UrutanUnggulan) supaya tidak menumpuk di satu sisi. Bentuk yang
     * dipakai kejuaraan resmi, dan bentuk yang dikenal pembaca bagan.
     */
    case Gugur = 'gugur';

    /**
     * Tidak dibulatkan sama sekali: ukuran bagan = jumlah peserta.
     *
     * Peserta dipasangkan berurutan dari tempat undian, dan kalau jumlah
     * peserta satu babak ganjil, peserta di TEMPAT TERAKHIR melenggang ke
     * babak berikutnya. Akibat yang dicari: seluruh peserta bertanding di
     * babak pertama. Pada bagan 2^n, sepuluh peserta berarti enam bye --
     * enam orang yang datang jauh-jauh lalu naik ke babak kedua tanpa pernah
     * menginjak matras, dan itu justru kebalikan dari tujuan kejuaraan
     * pemasalan.
     *
     * Harga yang dibayar, dan panitia perlu tahu: pada jumlah ganjil, tempat
     * terakhir bisa melenggang lebih dari sekali. Sembilan peserta berarti
     * satu orang melenggang tiga kali lalu bertanding sekali di final. Yang
     * menahannya cuma undian acak.
     */
    case Pemasalan = 'pemasalan';

    public function label(): string
    {
        return match ($this) {
            self::Gugur => 'Gugur (bagan pangkat dua, sisanya bye)',
            /*
             * Label lamanya berbunyi "semua bertanding di babak pertama".
             * Itu benar hanya pada jumlah peserta genap; pada jumlah ganjil
             * tempat terakhir tetap melenggang, persis seperti bye -- dan
             * pengendali yang membaca janji itu lalu melihat satu peserta
             * tidak bertanding akan mengira baganya salah tersusun.
             */
            self::Pemasalan => 'Pemasalan (bagan seukuran peserta, ganjil menyisakan satu)',
        };
    }

    /** Penjelasan sebaris untuk dialog penyusunan bagan. */
    public function keterangan(): string
    {
        return match ($this) {
            self::Gugur => 'Bagan dibulatkan ke 8, 16, 32, dan seterusnya. Tempat yang tersisa jadi bye yang disebar merata.',
            self::Pemasalan => 'Bagan seukuran jumlah peserta. Semua bertanding di babak pertama; kalau ganjil, tempat terakhir melenggang.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $m) => [$m->value => $m->label()])
            ->all();
    }
}
