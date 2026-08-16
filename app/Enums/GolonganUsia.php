<?php

namespace App\Enums;

/**
 * Penggolongan pertandingan menurut umur — Pasal 2 Peraturan Pertandingan
 * Pencak Silat Nasional Tahun 2025.
 *
 * Umur dihitung pada bulan pertandingan dimulai, bukan pada saat pendaftaran.
 * Batas atas bersifat inklusif dan batas bawah eksklusif, mengikuti rumusan
 * naskah "di atas 8 s.d 11 tahun".
 */
enum GolonganUsia: string
{
    case PraUsiaDini = 'pra_usia_dini';
    case UsiaDini1 = 'usia_dini_1';
    case UsiaDini2 = 'usia_dini_2';
    case PraRemaja = 'pra_remaja';
    case Remaja = 'remaja';
    case Dewasa = 'dewasa';
    case Master1 = 'master_1';
    case Master2 = 'master_2';

    public function label(): string
    {
        return match ($this) {
            self::PraUsiaDini => 'Pra Usia Dini',
            self::UsiaDini1 => 'Usia Dini 1',
            self::UsiaDini2 => 'Usia Dini 2',
            self::PraRemaja => 'Pra Remaja',
            self::Remaja => 'Remaja',
            self::Dewasa => 'Dewasa',
            self::Master1 => 'Master 1',
            self::Master2 => 'Master 2',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PraUsiaDini => 'Berumur 5 tahun ke bawah.',
            self::UsiaDini1 => 'Berumur di atas 5 sampai dengan 8 tahun.',
            self::UsiaDini2 => 'Berumur di atas 8 sampai dengan 11 tahun.',
            self::PraRemaja => 'Berumur di atas 11 sampai dengan 14 tahun.',
            self::Remaja => 'Berumur di atas 14 sampai dengan 17 tahun.',
            self::Dewasa => 'Berumur di atas 17 sampai dengan 35 tahun.',
            self::Master1 => 'Berumur di atas 35 sampai dengan 45 tahun.',
            self::Master2 => 'Berumur 45 tahun ke atas.',
        };
    }

    /**
     * Batas umur dalam tahun: [batas bawah eksklusif, batas atas inklusif].
     * Nilai null berarti tidak ada batas di sisi itu.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function batasUmur(): array
    {
        return match ($this) {
            self::PraUsiaDini => [null, 5],
            self::UsiaDini1 => [5, 8],
            self::UsiaDini2 => [8, 11],
            self::PraRemaja => [11, 14],
            self::Remaja => [14, 17],
            self::Dewasa => [17, 35],
            self::Master1 => [35, 45],
            self::Master2 => [45, null],
        };
    }

    public function mencakupUmur(int $umur): bool
    {
        [$bawah, $atas] = $this->batasUmur();

        return ($bawah === null || $umur > $bawah)
            && ($atas === null || $umur <= $atas);
    }

    public static function untukUmur(int $umur): ?self
    {
        foreach (self::cases() as $golongan) {
            if ($golongan->mencakupUmur($umur)) {
                return $golongan;
            }
        }

        return null;
    }

    /**
     * Apakah golongan ini mempertandingkan kategori Tanding?
     *
     * Pra Usia Dini hanya mempertandingkan Jurus Tunggal Bebas (Pasal 3.1).
     */
    public function adaTanding(): bool
    {
        return $this !== self::PraUsiaDini;
    }

    /**
     * Apakah kelas Tanding golongan ini dibagi menurut berat badan?
     *
     * Usia Dini 1 tidak memakai kelas berat. Pesilat dipasangkan berdasarkan
     * kesamaan usia, tinggi, dan berat badan dengan toleransi selisih 1 tahun,
     * 3 cm, dan 1 kg (Pasal 3.2.a).
     */
    public function pakaiKelasBerat(): bool
    {
        return $this->adaTanding() && $this !== self::UsiaDini1;
    }

    /**
     * Apakah golongan ini menjalani timbang badan?
     *
     * Pasal 2.4.a: Pra Usia Dini dan Usia Dini 1 tidak melakukan timbang badan.
     */
    public function adaTimbangBadan(): bool
    {
        return ! in_array($this, [self::PraUsiaDini, self::UsiaDini1], strict: true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> nilai => label */
    public static function options(): array
    {
        return array_combine(self::values(), array_map(
            static fn (self $golongan): string => $golongan->label(),
            self::cases(),
        ));
    }
}
