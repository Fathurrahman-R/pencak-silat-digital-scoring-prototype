<?php

namespace App\Support\Bagan;

/**
 * Menerjemahkan ronde bagan jadi tahap penampilan Jurus.
 *
 * Naskah Pasal 12.1.b.2-5 menetapkan jurus DAN durasi yang berbeda tiap tahap:
 * Penyisihan 1 tangan kosong, Penyisihan 2 dan Perempat Final senjata,
 * Semi Final diundi, Final lengkap. Tahap yang salah berarti waktu acuan yang
 * salah dipakai pemecah seri "waktu terdekat ke acuan" (Pasal 12.1.f.2.b).
 *
 * Dihitung dari ronde, bukan disimpan: bagan berukuran 8 punya semifinal di
 * ronde 2, bagan berukuran 32 di ronde 4. Menyimpannya sebagai kolom berarti
 * satu hal lagi yang bisa bertentangan dengan kenyataan saat bagan disusun
 * ulang.
 */
class TahapBaganJurus
{
    public const PENYISIHAN_1 = 'penyisihan_1';

    public const PENYISIHAN_2 = 'penyisihan_2';

    public const PEREMPAT_FINAL = 'perempat_final';

    public const SEMIFINAL = 'semifinal';

    public const FINAL = 'final';

    public static function untuk(int $ronde, int $ukuranBagan): string
    {
        $jumlahRonde = (int) log($ukuranBagan, 2);

        // Dihitung dari BELAKANG: final selalu ronde terakhir, apa pun ukuran
        // bagannya.
        return match ($jumlahRonde - $ronde) {
            0 => self::FINAL,
            1 => self::SEMIFINAL,
            2 => self::PEREMPAT_FINAL,
            3 => self::PENYISIHAN_2,
            default => self::PENYISIHAN_1,
        };
    }

    public static function label(string $tahap): string
    {
        return match ($tahap) {
            self::FINAL => 'Final',
            self::SEMIFINAL => 'Semi Final',
            self::PEREMPAT_FINAL => 'Perempat Final',
            self::PENYISIHAN_2 => 'Penyisihan 2',
            default => 'Penyisihan 1',
        };
    }
}
