<?php

namespace App\Enums;

/** State timer satu babak, dipegang server. */
enum StatusBabak: string
{
    case BelumMulai = 'belum_mulai';
    case Berjalan = 'berjalan';
    case Jeda = 'jeda';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::BelumMulai => 'Belum mulai',
            self::Berjalan => 'Berjalan',
            self::Jeda => 'Jeda',
            self::Selesai => 'Selesai',
        };
    }
}
