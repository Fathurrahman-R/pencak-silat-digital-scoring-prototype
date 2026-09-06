<?php

namespace App\Enums;

use App\Models\SilatMatch;

/**
 * Bentuk jawaban atas protes manajer yang diterima -- Pasal 15 ayat 4 huruf c.e.
 *
 * Protes yang diterima WAJIB menyebut salah satunya. Naskah tidak menyediakan
 * pilihan "diterima tanpa akibat": kalau protesnya benar, ada sesuatu yang
 * harus terjadi berikutnya di gelanggang.
 */
enum AkibatProtes: string
{
    /** Ada unsur kesengajaan dan tenaga teknis terbukti melanggar. */
    case UbahHasil = 'ubah_hasil';

    /** Kesalahan terbukti, bukan kesengajaan -- kategori Tanding. */
    case BabakTambahan = 'babak_tambahan';

    /** Kesalahan terbukti, bukan kesengajaan -- kategori Jurus. */
    case PenampilanUlang = 'penampilan_ulang';

    public function label(): string
    {
        return match ($this) {
            self::UbahHasil => 'Mengubah hasil secara langsung',
            self::BabakTambahan => 'Menambah satu babak',
            self::PenampilanUlang => 'Penampilan kembali',
        };
    }

    /**
     * Akibat yang masuk akal untuk kategori partai ini.
     *
     * Penampilan ulang tidak berarti apa-apa untuk Tanding, dan babak tambahan
     * tidak berarti apa-apa untuk Jurus. Menawarkan keduanya di mana-mana
     * berarti menawarkan pilihan yang akan ditolak sendiri saat diterapkan.
     *
     * @return array<int, self>
     */
    public static function untukTanding(): array
    {
        return [self::UbahHasil, self::BabakTambahan];
    }

    /** @return array<int, self> */
    public static function untukJurus(): array
    {
        return [self::UbahHasil, self::PenampilanUlang];
    }

    public function berlakuUntuk(SilatMatch $match): bool
    {
        return in_array($this, self::untukTanding(), strict: true);
    }
}
