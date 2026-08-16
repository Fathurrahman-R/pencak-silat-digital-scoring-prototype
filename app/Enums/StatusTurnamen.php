<?php

namespace App\Enums;

/**
 * Daur hidup satu kejuaraan.
 *
 * Statusnya menentukan apa yang masih boleh berubah, bukan sekadar label:
 * setelan peraturan dan tarif hanya bisa disunting selama masih Draf, karena
 * mengubah aturan main di tengah kejuaraan berarti mengubah dasar perhitungan
 * partai yang sudah terlanjur dinilai.
 */
enum StatusTurnamen: string
{
    case Draf = 'draf';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Berjalan => 'success',
            self::Selesai => 'info',
        };
    }

    /** Setelan peraturan, kelas, dan tarif hanya boleh diubah selama draf. */
    public function bolehUbahAturan(): bool
    {
        return $this === self::Draf;
    }

    /**
     * Transisi yang sah dari status ini.
     *
     * Sengaja searah. Menarik kejuaraan yang sudah berjalan kembali ke draf
     * akan membuka kunci setelan peraturan sementara ada partai yang sudah
     * dinilai memakai setelan lama.
     *
     * @return array<int, self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Draf => [self::Berjalan],
            self::Berjalan => [self::Selesai],
            self::Selesai => [],
        };
    }

    public function bisaPindahKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $status): string => $status->label(), self::cases()),
        );
    }
}
