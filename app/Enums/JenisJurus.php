<?php

namespace App\Enums;

/**
 * Nomor yang dipertandingkan pada kategori Jurus — Pasal 12.
 *
 * Naskah 2025 memakai istilah "Jurus", bukan "Seni". Istilah lama sengaja
 * tidak dipakai di mana pun supaya nama di layar sama persis dengan nama di
 * naskah, dan panitia tidak perlu menerjemahkan sendiri saat mencocokkan.
 */
enum JenisJurus: string
{
    case Tunggal = 'tunggal';
    case TunggalBebas = 'tunggal_bebas';
    case Ganda = 'ganda';
    case ReguA = 'regu_a';
    case ReguB = 'regu_b';
    case SoloKreatif = 'solo_kreatif';

    public function label(): string
    {
        return match ($this) {
            self::Tunggal => 'Jurus Tunggal',
            self::TunggalBebas => 'Jurus Tunggal Bebas',
            self::Ganda => 'Jurus Ganda',
            self::ReguA => 'Jurus Regu A',
            self::ReguB => 'Jurus Regu B',
            self::SoloKreatif => 'Solo Kreatif',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Tunggal => 'Satu pesilat memperagakan jurus baku tangan kosong dan bersenjata.',
            self::TunggalBebas => 'Satu pesilat memperagakan rangkaian jurus yang disusun sendiri.',
            self::Ganda => 'Dua pesilat memperagakan kekayaan teknik serang bela.',
            self::ReguA => 'Tiga pesilat memperagakan jurus baku regu nomor 1 sampai 6.',
            self::ReguB => 'Tiga pesilat memperagakan jurus baku regu nomor 7 sampai 12.',
            self::SoloKreatif => 'Satu pesilat memperagakan rangkaian kreasi sendiri.',
        };
    }

    /**
     * Berapa pesilat yang membentuk satu penampilan.
     *
     * Angka ini menentukan cara menagih: nomor beregu dikenakan biaya per tim,
     * bukan per orang.
     */
    public function jumlahPesilat(): int
    {
        return match ($this) {
            self::Tunggal, self::TunggalBebas, self::SoloKreatif => 1,
            self::Ganda => 2,
            self::ReguA, self::ReguB => 3,
        };
    }

    public function beregu(): bool
    {
        return $this->jumlahPesilat() > 1;
    }

    /**
     * Kunci waktu acuan di config/scoring.php.
     *
     * Hanya Tunggal dan Tunggal Bebas yang waktunya diatur naskah per tahap.
     * Nomor lain memakai waktu penampilan yang ditetapkan panitia, sehingga
     * disimpan di barisnya sendiri, bukan diambil dari konfigurasi.
     */
    public function kunciWaktuAcuan(): ?string
    {
        return match ($this) {
            self::Tunggal => 'tunggal',
            self::TunggalBebas => 'tunggal_bebas',
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $j): string => $j->label(), self::cases()),
        );
    }
}
