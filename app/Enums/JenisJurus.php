<?php

namespace App\Enums;

/**
 * Nomor yang dipertandingkan pada kategori Jurus — Pasal 10 dan Pasal 12.
 *
 * Naskah 2025 memakai istilah "Jurus", bukan "Seni". Istilah lama sengaja
 * tidak dipakai di mana pun supaya nama di layar sama persis dengan nama di
 * naskah, dan panitia tidak perlu menerjemahkan sendiri saat mencocokkan.
 *
 * Golongan muda memisahkan nomor tangan kosong dan bersenjata menjadi dua
 * nomor yang berdiri sendiri, sementara Remaja dan Dewasa mempertandingkannya
 * sebagai satu nomor utuh. Keduanya karena itu punya case sendiri: menyatukan
 * mereka berarti satu penampilan Usia Dini 2 kehilangan medalinya.
 */
enum JenisJurus: string
{
    // Remaja, Dewasa, Master — nomor utuh.
    case Tunggal = 'tunggal';
    case TunggalBebas = 'tunggal_bebas';
    case Ganda = 'ganda';
    case Regu = 'regu';

    // Usia Dini dan Pra Remaja — dipecah menurut senjata.
    case TunggalTanganKosong = 'tunggal_tangan_kosong';
    case TunggalSenjata = 'tunggal_senjata';
    case GandaTanganKosong = 'ganda_tangan_kosong';
    case GandaSenjata = 'ganda_senjata';
    case ReguA = 'regu_a';
    case ReguB = 'regu_b';

    case SoloKreatif = 'solo_kreatif';

    public function label(): string
    {
        return match ($this) {
            self::Tunggal => 'Jurus Tunggal',
            self::TunggalBebas => 'Jurus Tunggal Bebas',
            self::Ganda => 'Jurus Ganda',
            self::Regu => 'Jurus Regu',
            self::TunggalTanganKosong => 'Jurus Tunggal Tangan Kosong',
            self::TunggalSenjata => 'Jurus Tunggal Senjata',
            self::GandaTanganKosong => 'Jurus Ganda Tangan Kosong',
            self::GandaSenjata => 'Jurus Ganda Senjata',
            self::ReguA => 'Jurus Regu A',
            self::ReguB => 'Jurus Regu B',
            self::SoloKreatif => 'Solo Kreatif',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Tunggal => 'Satu pesilat memperagakan jurus baku tangan kosong dan bersenjata.',
            self::TunggalBebas => 'Satu pesilat memperagakan koreografi jurus dengan senjata Nusantara.',
            self::Ganda => 'Dua pesilat memperagakan kekayaan teknik serang bela, tangan kosong lalu bersenjata.',
            self::Regu => 'Tiga pesilat memperagakan jurus baku regu dengan tangan kosong.',
            self::TunggalTanganKosong => 'Satu pesilat memperagakan jurus baku tunggal tangan kosong.',
            self::TunggalSenjata => 'Satu pesilat memperagakan jurus baku tunggal dengan toya dan golok.',
            self::GandaTanganKosong => 'Dua pesilat memperagakan teknik serang bela tangan kosong.',
            self::GandaSenjata => 'Dua pesilat memperagakan teknik serang bela bersenjata.',
            self::ReguA => 'Tiga pesilat memperagakan jurus baku regu nomor 1 sampai 6.',
            self::ReguB => 'Tiga pesilat memperagakan jurus baku regu nomor 7 sampai 12.',
            self::SoloKreatif => 'Satu pesilat memperagakan rangkaian kreasi sendiri.',
        };
    }

    /**
     * Berapa pesilat yang membentuk satu penampilan.
     *
     * Angka ini menentukan cara menagih: nomor beregu dikenakan biaya per tim,
     * bukan per orang.
     */
    public function jumlahPesilat(): int
    {
        return match ($this) {
            self::Ganda, self::GandaTanganKosong, self::GandaSenjata => 2,
            self::Regu, self::ReguA, self::ReguB => 3,
            default => 1,
        };
    }

    public function beregu(): bool
    {
        return $this->jumlahPesilat() > 1;
    }

    /**
     * Kunci waktu acuan di config/scoring.php.
     *
     * Hanya Tunggal dan Tunggal Bebas yang waktunya berbeda per tahap
     * pertandingan — Pasal 12.1.c. Nomor lain memakai satu waktu tetap; lihat
     * waktuBakuMs().
     */
    public function kunciWaktuAcuan(): ?string
    {
        return match ($this) {
            self::Tunggal => 'tunggal',
            self::TunggalBebas => 'tunggal_bebas',
            default => null,
        };
    }

    /**
     * Waktu penampilan tetap dalam milidetik — Pasal 10 ayat 3.
     *
     * Berlaku untuk nomor golongan muda, yang waktunya sama di semua tahap.
     * Solo Kreatif dikecualikan: naskah memberinya rentang 1 sampai 2 menit,
     * bukan satu angka, jadi waktunya ditetapkan panitia per kejuaraan.
     */
    public function waktuBakuMs(): ?int
    {
        return match ($this) {
            self::TunggalTanganKosong => 90_000,
            self::TunggalSenjata,
            self::GandaTanganKosong,
            self::GandaSenjata,
            self::ReguA,
            self::ReguB => 110_000,
            default => null,
        };
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
