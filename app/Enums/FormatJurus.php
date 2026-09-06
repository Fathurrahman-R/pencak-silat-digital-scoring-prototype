<?php

namespace App\Enums;

/**
 * Bentuk pertandingan satu nomor Jurus.
 *
 * Naskah 2025 hanya mengenal satu: sistem gugur (Pasal 12.1.b.1, dan dua kali
 * ditegaskan lagi di bagian pemecah seri). `Penampilan` tetap ada karena
 * kejuaraan yang sudah berjalan disusun dengan bentuk itu, dan mengubah
 * bentuknya di tengah jalan berarti membatalkan hasil yang sudah tercatat.
 */
enum FormatJurus: string
{
    /**
     * Peserta tampil bergiliran, lalu diperingkat dari nilainya.
     *
     * Bentuk yang dipakai sistem ini sebelum naskah 2025 dibaca ulang.
     */
    case Penampilan = 'penampilan';

    /**
     * Dua sudut bertemu dalam satu battle, yang unggul naik ke ronde berikutnya.
     *
     * Sudut biru tampil lebih dulu, disusul sudut merah (Pasal 12.1.d.7).
     */
    case Battle = 'battle';

    public function label(): string
    {
        return match ($this) {
            self::Penampilan => 'Penampilan (peringkat)',
            self::Battle => 'Battle (sistem gugur)',
        };
    }

    public function pakaiBagan(): bool
    {
        return $this === self::Battle;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $f) => [$f->value => $f->label()])
            ->all();
    }
}
