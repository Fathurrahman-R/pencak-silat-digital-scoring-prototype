<?php

namespace App\Enums;

/**
 * Daur hidup tagihan satu kontingen.
 *
 *   draf                 ikut berubah setiap pendaftaran ditambah atau dihapus
 *   menunggu_pembayaran  nominal terkunci, pendaftaran dibekukan
 *   lunas                dibayar; pendaftaran boleh diverifikasi panitia
 *
 * "Boleh direvisi sebelum dibayar" diartikan sebagai sebelum sesi pembayaran
 * dibuat, bukan sebelum uangnya masuk. Tanpa itu ada celah: official menekan
 * bayar untuk satu nominal, menambah atlet, lalu uang nominal lama yang
 * masuk — dan tagihannya sudah berubah.
 */
enum StatusInvoice: string
{
    case Draf = 'draf';
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Lunas = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::Lunas => 'Lunas',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::MenungguPembayaran => 'warning',
            self::Lunas => 'success',
        };
    }

    /** Selama draf, isi tagihan disusun ulang tiap kali pendaftaran berubah. */
    public function ikutPendaftaran(): bool
    {
        return $this === self::Draf;
    }

    /**
     * Pendaftaran kontingen dibekukan selama menunggu pembayaran.
     *
     * Yang sudah lunas juga tidak boleh bertambah begitu saja — penambahan
     * sesudahnya berarti tagihan baru, bukan menyusup ke tagihan yang sudah
     * dibayar.
     */
    public function membekukanPendaftaran(): bool
    {
        return $this !== self::Draf;
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
