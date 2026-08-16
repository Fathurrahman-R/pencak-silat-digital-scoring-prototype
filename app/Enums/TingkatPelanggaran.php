<?php

namespace App\Enums;

/** Sebab sanksi -- Pasal 11.6.d. Pelanggaran adalah sebab, hukuman adalah akibat. */
enum TingkatPelanggaran: string
{
    case Ringan = 'ringan';
    case Sedang = 'sedang';
    case Berat = 'berat';

    public function label(): string
    {
        return match ($this) {
            self::Ringan => 'Ringan',
            self::Sedang => 'Sedang',
            self::Berat => 'Berat',
        };
    }

    /** Tahap hukuman yang langsung dijatuhkan akibat tingkat pelanggaran ini. */
    public function tingkatHukuman(): TingkatHukuman
    {
        return match ($this) {
            self::Ringan => TingkatHukuman::Pembinaan,
            self::Sedang => TingkatHukuman::Teguran,
            self::Berat => TingkatHukuman::Peringatan,
        };
    }
}
