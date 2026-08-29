<?php

namespace App\Enums;

/**
 * Jawaban satu juri atas satu verifikasi.
 *
 * Nilainya sengaja sejajar dengan App\Enums\Sudut ('red' dan 'blue') supaya
 * jawaban bisa diterjemahkan ke sudut tanpa tabel pemetaan, ditambah satu
 * pilihan yang tidak punya padanan di sana.
 *
 * "Tidak ada" bukan sekadar ketiadaan jawaban -- ia jawaban penuh, dan harus
 * bisa menang. Juri yang melihat kedua pesilat jatuh bersamaan, atau melihat
 * jatuhan yang tidak sah, sedang menjawab pertanyaannya, bukan menghindarinya.
 * Kalau "tidak ada" tidak dihitung, dua juri yang melihat hal itu akan
 * kalah oleh satu juri yang menunjuk sudut, dan hasilnya justru kebalikan
 * dari yang dilihat mayoritas.
 *
 * Juri yang belum menjawab tidak punya baris sama sekali -- itu keadaan lain,
 * dan tidak boleh tertukar dengan "tidak ada".
 */
enum JawabanVerifikasi: string
{
    case Merah = 'red';
    case Biru = 'blue';
    case TidakAda = 'tidak_ada';

    public function label(): string
    {
        return match ($this) {
            self::Merah => 'Sudut merah',
            self::Biru => 'Sudut biru',
            self::TidakAda => 'Tidak ada',
        };
    }

    /** Sudut yang dimaksud, atau null kalau jawabannya "tidak ada". */
    public function sudut(): ?Sudut
    {
        return match ($this) {
            self::Merah => Sudut::Merah,
            self::Biru => Sudut::Biru,
            self::TidakAda => null,
        };
    }
}
