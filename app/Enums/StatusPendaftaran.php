<?php

namespace App\Enums;

/**
 * Daur hidup satu pendaftaran atlet ke kelas atau nomor.
 *
 * Verifikasi adalah pintu terakhir sebelum atlet masuk bagan, dan pintu itu
 * hanya bisa dilewati kalau tagihan kontingennya sudah lunas — lihat
 * App\Models\Registration.
 */
enum StatusPendaftaran: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Terverifikasi = 'terverifikasi';
    case Ditolak = 'ditolak';
    case Gugur = 'gugur';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Terverifikasi => 'Terverifikasi',
            self::Ditolak => 'Ditolak',
            self::Gugur => 'Gugur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draf => 'Masih bisa disunting kontingen dan belum dilihat panitia.',
            self::Diajukan => 'Menunggu pemeriksaan berkas oleh panitia.',
            self::Terverifikasi => 'Sah dan berhak masuk bagan.',
            self::Ditolak => 'Berkas tidak memenuhi syarat; alasannya tercatat.',
            self::Gugur => 'Tidak lolos timbang badan atau mengundurkan diri.',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Diajukan => 'info',
            self::Terverifikasi => 'success',
            self::Ditolak, self::Gugur => 'danger',
        };
    }

    /** Hanya pendaftaran yang belum diperiksa panitia yang boleh disunting kontingen. */
    public function bolehDisuntingKontingen(): bool
    {
        return $this === self::Draf;
    }

    /** Yang berhak masuk bagan dan dijadwalkan bertanding. */
    public function sah(): bool
    {
        return $this === self::Terverifikasi;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $s): string => $s->label(), self::cases()),
        );
    }
}
