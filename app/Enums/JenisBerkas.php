<?php

namespace App\Enums;

/**
 * Berkas persyaratan peserta.
 *
 * Pasal 2 ayat 2: umur dibuktikan dengan akta kelahiran, ijazah, atau paspor.
 * Pasal 2 ayat 5: surat keterangan sehat wajib, diterbitkan paling lama satu
 * minggu sebelum pertandingan, dan pesilat yang tidak dapat menunjukkannya
 * dinyatakan diskualifikasi.
 */
enum JenisBerkas: string
{
    case BuktiUmur = 'bukti_umur';
    case SuratSehat = 'surat_sehat';
    case SuratTidakHamil = 'surat_tidak_hamil';
    case Foto = 'foto';

    public function label(): string
    {
        return match ($this) {
            self::BuktiUmur => 'Bukti umur',
            self::SuratSehat => 'Surat keterangan sehat',
            self::SuratTidakHamil => 'Surat pernyataan tidak hamil',
            self::Foto => 'Pas foto',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BuktiUmur => 'Akta kelahiran, ijazah, atau paspor — asli atau fotokopi berlegalisir (Pasal 2 ayat 2).',
            self::SuratSehat => 'Dari rumah sakit atau puskesmas berwenang, paling lama satu minggu sebelum pertandingan (Pasal 2 ayat 5).',
            self::SuratTidakHamil => 'Wajib bagi pesilat putri golongan Remaja dan Dewasa (Pasal 2 ayat 5 huruf c).',
            self::Foto => 'Dipakai di papan skor, overlay siaran, dan berita acara.',
        };
    }

    /**
     * Berkas yang wajib ada sebelum pendaftaran boleh diajukan.
     *
     * Surat tidak hamil hanya berlaku untuk sebagian peserta, jadi kewajibannya
     * ditentukan per atlet — lihat App\Models\Athlete::berkasWajib().
     *
     * @return list<self>
     */
    public static function wajibUmum(): array
    {
        return [self::BuktiUmur, self::SuratSehat];
    }

    public function tipeDiterima(): array
    {
        return $this === self::Foto
            ? ['jpg', 'jpeg', 'png']
            : ['jpg', 'jpeg', 'png', 'pdf'];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $j): string => $j->label(), self::cases()),
        );
    }
}
