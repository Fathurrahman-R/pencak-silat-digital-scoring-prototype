<?php

namespace App\Enums;

/**
 * Dua kategori yang dipertandingkan — Pasal 11 dan Pasal 12.
 *
 * Keduanya berbeda sampai ke akarnya: Tanding memakai konsensus tiga juri atas
 * kejadian yang berlangsung serentak, sedangkan Jurus memakai median nilai
 * dari juri berjumlah genap atas satu penampilan utuh. Karena itu keduanya
 * tidak pernah berbagi tabel penilaian maupun mesin skornya.
 */
enum KategoriPertandingan: string
{
    case Tanding = 'tanding';
    case Jurus = 'jurus';

    public function label(): string
    {
        return match ($this) {
            self::Tanding => 'Tanding',
            self::Jurus => 'Jurus',
        };
    }

    /** Kunci di config/scoring.php untuk komposisi juri kategori ini. */
    public function kunciJuri(): string
    {
        return $this->value;
    }

    /** Tanding dipertandingkan dalam bagan gugur; Jurus dinilai per penampilan. */
    public function pakaiBagan(): bool
    {
        return $this === self::Tanding;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $k): string => $k->label(), self::cases()),
        );
    }
}
