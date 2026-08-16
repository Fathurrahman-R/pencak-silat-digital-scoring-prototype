<?php

namespace App\Support\Pendaftaran;

/**
 * Hasil pemeriksaan kelayakan, beserta seluruh alasan penolakannya.
 *
 * Sengaja mengumpulkan semua pelanggaran sekaligus, bukan berhenti di yang
 * pertama. Official yang mendaftarkan puluhan atlet perlu tahu semua yang
 * salah dalam satu kali lihat — bukan memperbaiki satu, menyimpan, lalu
 * menemukan yang berikutnya.
 */
final class HasilKelayakan
{
    /** @param  list<string>  $alasan */
    private function __construct(public readonly array $alasan) {}

    public static function lolos(): self
    {
        return new self([]);
    }

    /** @param  list<string>  $alasan */
    public static function ditolak(array $alasan): self
    {
        return new self(array_values($alasan));
    }

    public function diterima(): bool
    {
        return $this->alasan === [];
    }

    public function ditolakSemua(): bool
    {
        return ! $this->diterima();
    }

    /** Alasan pertama, untuk tempat yang hanya muat satu baris. */
    public function alasanUtama(): ?string
    {
        return $this->alasan[0] ?? null;
    }

    public function pesan(): string
    {
        return implode(' ', $this->alasan);
    }
}
