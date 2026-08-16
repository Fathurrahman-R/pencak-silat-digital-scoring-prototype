<?php

namespace App\Enums;

/**
 * Nilai prestasi teknik -- Pasal 11.6.e. Hanya tiga: naskah 2025 tidak
 * mengenal nilai 4 (kuncian) maupun nilai gabungan 1+1/1+2/1+3 dari edisi
 * lama.
 */
enum JenisSerangan: string
{
    case Pukulan = 'pukulan';
    case Tendangan = 'tendangan';
    case Jatuhan = 'jatuhan';

    public function label(): string
    {
        return match ($this) {
            self::Pukulan => 'Pukulan',
            self::Tendangan => 'Tendangan',
            self::Jatuhan => 'Jatuhan',
        };
    }

    /** Nilai baku dari config/scoring.php -- 1, 2, atau 3. */
    public function nilai(): int
    {
        return config("scoring.tanding.nilai.{$this->value}");
    }
}
