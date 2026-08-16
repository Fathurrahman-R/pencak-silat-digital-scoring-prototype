<?php

namespace App\Enums;

/**
 * Sudut pesilat di gelanggang. Merah dan biru adalah identitas yang
 * ditetapkan peraturan, bukan sekadar urutan tampil.
 */
enum Sudut: string
{
    case Merah = 'red';
    case Biru = 'blue';

    public function label(): string
    {
        return match ($this) {
            self::Merah => 'Merah',
            self::Biru => 'Biru',
        };
    }

    public function lawan(): self
    {
        return $this === self::Merah ? self::Biru : self::Merah;
    }
}
