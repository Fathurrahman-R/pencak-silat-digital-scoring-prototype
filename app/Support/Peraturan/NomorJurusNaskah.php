<?php

namespace App\Support\Peraturan;

use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;

/**
 * Nomor kategori Jurus yang dipertandingkan tiap golongan usia.
 *
 * Sumber: Peraturan Pertandingan Pencak Silat Nasional Tahun 2025, Pasal 3
 * sampai Pasal 7 untuk daftar nomornya, Pasal 10 dan Pasal 12 untuk waktu
 * penampilannya.
 *
 * Semua nomor dipertandingkan untuk putra maupun putri tanpa kecuali, jadi
 * pembagian gender tidak muncul di sini — pemanggilnya yang menggandakan.
 */
final class NomorJurusNaskah
{
    /**
     * Nomor yang dipertandingkan satu golongan usia.
     *
     * @return list<JenisJurus>
     */
    public static function untuk(GolonganUsia $golongan): array
    {
        return match ($golongan) {
            // Pasal 3 ayat 1: satu-satunya nomor untuk golongan ini, dan
            // golongan ini tidak mengenal kategori Tanding sama sekali.
            GolonganUsia::PraUsiaDini => [
                JenisJurus::TunggalBebas,
            ],

            // Pasal 3 ayat 2 huruf b.
            GolonganUsia::UsiaDini1 => [
                JenisJurus::TunggalTanganKosong,
                JenisJurus::TunggalSenjata,
                JenisJurus::TunggalBebas,
            ],

            // Pasal 3 ayat 3 huruf b — mulai ada nomor beregu, dengan Regu A.
            GolonganUsia::UsiaDini2 => [
                JenisJurus::TunggalTanganKosong,
                JenisJurus::TunggalSenjata,
                JenisJurus::TunggalBebas,
                JenisJurus::GandaTanganKosong,
                JenisJurus::GandaSenjata,
                JenisJurus::ReguA,
            ],

            // Pasal 4 ayat 2 — sama dengan Usia Dini 2, tetapi Regu B.
            GolonganUsia::PraRemaja => [
                JenisJurus::TunggalTanganKosong,
                JenisJurus::TunggalSenjata,
                JenisJurus::TunggalBebas,
                JenisJurus::GandaTanganKosong,
                JenisJurus::GandaSenjata,
                JenisJurus::ReguB,
            ],

            // Pasal 5 ayat 2 dan Pasal 6 ayat 2 — nomor tidak lagi dipecah
            // menurut senjata; tangan kosong dan bersenjata jadi satu
            // penampilan utuh.
            GolonganUsia::Remaja,
            GolonganUsia::Dewasa,
            GolonganUsia::Master1,
            GolonganUsia::Master2 => [
                JenisJurus::Tunggal,
                JenisJurus::TunggalBebas,
                JenisJurus::Ganda,
                JenisJurus::Regu,
            ],
        };
    }

    /**
     * Waktu acuan yang perlu disimpan di baris nomor, dalam milidetik.
     *
     * Kosong berarti waktunya tidak tetap dan dibaca dari tempat lain: nomor
     * Tunggal dan Tunggal Bebas punya waktu berbeda per tahap pertandingan,
     * sedangkan Solo Kreatif hanya diberi rentang 1 sampai 2 menit sehingga
     * panitia yang menetapkannya.
     */
    public static function waktuAcuanMs(JenisJurus $jenis): ?int
    {
        return $jenis->waktuBakuMs();
    }
}
