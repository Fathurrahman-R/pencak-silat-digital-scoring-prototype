<?php

namespace App\Enums;

/**
 * Pembagian putra/putri.
 *
 * Dipakai atlet maupun kelas pertandingan. Naskah 2025 memisahkan seluruh
 * kelas tanding dan nomor jurus berdasarkan ini tanpa kecuali, jadi tidak ada
 * nomor campuran.
 */
enum JenisKelamin: string
{
    case Putra = 'putra';
    case Putri = 'putri';

    public function label(): string
    {
        return match ($this) {
            self::Putra => 'Putra',
            self::Putri => 'Putri',
        };
    }

    /** Sebutan untuk satu orang, dipakai pada formulir data atlet. */
    public function labelOrang(): string
    {
        return match ($this) {
            self::Putra => 'Laki-laki',
            self::Putri => 'Perempuan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $jk): string => $jk->label(), self::cases()),
        );
    }
}
