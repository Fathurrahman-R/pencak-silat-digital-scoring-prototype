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

    /*
     * `pakaiBagan()` DIHAPUS dari sini, tidak diperbaiki.
     *
     * Ia dulu menjawab false untuk Jurus, dan jawaban itu sudah tidak benar
     * sejak nomor Jurus mengenal format `battle` -- yang memakai bagan gugur
     * dengan aritmetika, pohon, dan lembar cetak yang sama persis dengan
     * Tanding (Pasal 12.1.b.1).
     *
     * Yang lebih penting: pertanyaannya sendiri salah bentuk. "Kategori ini
     * pakai bagan?" tidak bisa dijawab per kategori, karena yang menentukan
     * adalah `jurus_events.format` -- per NOMOR. Membetulkan nilainya hanya
     * akan menyisakan metode yang benar untuk sebagian nomor dan keliru untuk
     * sisanya, dan yang menemukannya lain kali akan mengiranya jawaban sah.
     *
     * Penggantinya App\Enums\FormatJurus::pakaiBagan().
     */

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $k): string => $k->label(), self::cases()),
        );
    }
}
