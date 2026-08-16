<?php

namespace App\Enums;

/** Tahapan hukuman -- Pasal 11.6.d.4: Pembinaan, Teguran, Peringatan. */
enum TingkatHukuman: string
{
    case Pembinaan = 'pembinaan';
    case Teguran = 'teguran';
    case Peringatan = 'peringatan';

    public function label(): string
    {
        return match ($this) {
            self::Pembinaan => 'Pembinaan',
            self::Teguran => 'Teguran',
            self::Peringatan => 'Peringatan',
        };
    }
}
