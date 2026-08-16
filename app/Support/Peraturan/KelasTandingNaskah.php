<?php

namespace App\Support\Peraturan;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;

/**
 * Tabel kelas tanding sebagaimana tertulis di naskah 2025.
 *
 * Sumber: Peraturan Pertandingan Pencak Silat Nasional Tahun 2025, Pasal 3
 * sampai Pasal 7. Berkasnya ada di `document/`.
 *
 * Berkas ini hanya berisi angka, tanpa akses basis data. Yang menuangkannya
 * ke satu kejuaraan adalah App\Actions\Turnamen\SusunKelasTanding, sehingga
 * panitia tetap bisa mematikan atau menyunting kelas tanpa menyentuh salinan
 * naskah ini.
 *
 * Dua golongan sengaja tidak punya baris di sini:
 *   Pra Usia Dini  hanya mempertandingkan Jurus Tunggal Bebas (Pasal 3 ayat 1)
 *   Usia Dini 1    bertanding tanpa kelas berat; lawan dijodohkan berdasarkan
 *                  kedekatan usia, tinggi, dan berat (Pasal 3 ayat 2)
 */
final class KelasTandingNaskah
{
    /**
     * Seluruh kelas untuk satu golongan usia dan jenis kelamin.
     *
     * @return list<array{code: string, name: string, weight_min: float|null, weight_max: float|null, weight_min_exclusive: bool, weight_max_inclusive: bool}>
     */
    public static function untuk(GolonganUsia $golongan, JenisKelamin $jenisKelamin): array
    {
        $baris = match ($golongan) {
            GolonganUsia::UsiaDini2 => self::usiaDini2(),
            GolonganUsia::PraRemaja => self::praRemaja(),
            GolonganUsia::Remaja => self::remaja($jenisKelamin),
            GolonganUsia::Dewasa,
            GolonganUsia::Master1,
            GolonganUsia::Master2 => self::dewasa($jenisKelamin),
            default => [],
        };

        return array_values($baris);
    }

    /** Golongan yang punya kelas berat sama sekali. */
    public static function golonganBerkelas(): array
    {
        return array_values(array_filter(
            GolonganUsia::cases(),
            static fn (GolonganUsia $g): bool => $g->adaTanding() && $g->pakaiKelasBerat(),
        ));
    }

    /**
     * Pasal 3 ayat 3 — 20 nomor, sama untuk putra dan putri.
     *
     * Kelas terendah ditulis "26 kg sampai 28 kg", jadi batas bawahnya
     * inklusif; sisanya "Diatas ... sampai ...".
     */
    private static function usiaDini2(): array
    {
        return [
            self::kelas('A', 26, 28, minEksklusif: false),
            ...self::tangga(mulaiKode: 'B', dari: 28, sampai: 64, langkah: 2),
            self::kelas('Open', 64, 68),
        ];
    }

    /** Pasal 4 — 17 nomor, sama untuk putra dan putri. */
    private static function praRemaja(): array
    {
        return [
            self::kelas('A', 30, 33, minEksklusif: false),
            ...self::tangga(mulaiKode: 'B', dari: 33, sampai: 78, langkah: 3),
            self::kelas('Open', 78, 84),
        ];
    }

    /**
     * Pasal 5 — putra 15 nomor, putri 13 nomor.
     *
     * Keduanya berbagi tangga yang sama sampai kelas J; setelah itu putra
     * berlanjut ke K dan L sedangkan putri langsung masuk kelas terbuka.
     */
    private static function remaja(JenisKelamin $jenisKelamin): array
    {
        $dasar = [
            self::kelas('<39', null, 39, maxInklusif: false),
            self::kelas('A', 39, 43, minEksklusif: false),
            ...self::tangga(mulaiKode: 'B', dari: 43, sampai: 79, langkah: 4),
        ];

        return $jenisKelamin === JenisKelamin::Putra
            ? [
                ...$dasar,
                self::kelas('K', 79, 83),
                self::kelas('L', 83, 87),
                self::kelas('Open 1', 87, 100),
                self::kelas('Open 2', 100, null),
            ]
            : [
                ...$dasar,
                self::kelas('Open 1', 79, 92),
                self::kelas('Open 2', 92, null),
            ];
    }

    /**
     * Pasal 6 dan Pasal 7 — putra 13 nomor, putri 11 nomor.
     *
     * Master 1 dan Master 2 memakai tabel yang sama persis dengan Dewasa.
     * Yang membedakan ketiganya hanya jumlah dan durasi babak, dan itu ada di
     * config/scoring.php.
     */
    private static function dewasa(JenisKelamin $jenisKelamin): array
    {
        $dasar = [
            self::kelas('<45', null, 45, maxInklusif: false),
            self::kelas('A', 45, 50, minEksklusif: false),
            ...self::tangga(mulaiKode: 'B', dari: 50, sampai: 85, langkah: 5),
        ];

        return $jenisKelamin === JenisKelamin::Putra
            ? [
                ...$dasar,
                self::kelas('I', 85, 90),
                self::kelas('J', 90, 95),
                self::kelas('Open 1', 95, 110),
                self::kelas('Open 2', 110, null),
            ]
            : [
                ...$dasar,
                self::kelas('Open 1', 85, 100),
                self::kelas('Open 2', 100, null),
            ];
    }

    /**
     * Deret kelas berjarak tetap dengan rumusan "Diatas X kg sampai Y kg".
     *
     * `$dari` adalah batas bawah kelas pertama dan `$sampai` batas atas kelas
     * terakhir, sehingga angkanya bisa dibaca berdampingan dengan naskah tanpa
     * menghitung mundur.
     */
    private static function tangga(string $mulaiKode, int $dari, int $sampai, int $langkah): array
    {
        $hasil = [];
        $kode = $mulaiKode;

        for ($bawah = $dari; $bawah < $sampai; $bawah += $langkah) {
            $hasil[] = self::kelas($kode, $bawah, $bawah + $langkah);
            $kode = chr(ord($kode) + 1);
        }

        return $hasil;
    }

    private static function kelas(
        string $kode,
        ?float $min,
        ?float $max,
        bool $minEksklusif = true,
        bool $maxInklusif = true,
    ): array {
        return [
            'code' => $kode,
            'name' => "Kelas {$kode}",
            'weight_min' => $min,
            'weight_max' => $max,
            'weight_min_exclusive' => $minEksklusif,
            'weight_max_inclusive' => $maxInklusif,
        ];
    }
}
