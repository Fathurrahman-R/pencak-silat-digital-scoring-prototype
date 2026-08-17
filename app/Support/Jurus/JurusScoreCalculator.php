<?php

namespace App\Support\Jurus;

use App\Models\JurusPerformance;
use Illuminate\Support\Collection;

/**
 * Skor akhir Jurus -- Pasal 12.1.f: median dari nilai seluruh juri, dikurangi
 * pengurangan yang berlaku, dengan diskualifikasi ditunjukkan skor 0,00.
 *
 * Median dipakai apa adanya, BUKAN membuang nilai tertinggi dan terendah
 * lalu menjumlahkan sisanya -- itu anggapan dari edisi peraturan lama yang
 * tidak berlaku lagi di naskah 2025.
 */
class JurusScoreCalculator
{
    public function skorAkhir(JurusPerformance $performance): float
    {
        if ($performance->didiskualifikasi) {
            return (float) config('scoring.jurus.skor_diskualifikasi');
        }

        $skor = $this->median($performance) - $this->totalPengurangan($performance);

        return max(0.0, round($skor, 2));
    }

    /**
     * Median seluruh nilai juri. Jumlah juri selalu genap (Pasal 16.1.b),
     * jadi mediannya adalah rata-rata dua nilai tengah.
     */
    public function median(JurusPerformance $performance): float
    {
        $nilai = $this->nilaiTerurut($performance);
        $n = $nilai->count();

        if ($n === 0) {
            return 0.0;
        }

        $tengah = intdiv($n, 2);

        if ($n % 2 === 0) {
            return round(($nilai[$tengah - 1] + $nilai[$tengah]) / 2, 4);
        }

        return $nilai[$tengah];
    }

    public function totalPengurangan(JurusPerformance $performance): float
    {
        return (float) $performance->deductions()->berlaku()->sum('jumlah');
    }

    /** Standar deviasi populasi nilai juri -- dipakai pemecah seri (Pasal 12.1.f.2). */
    public function standarDeviasi(JurusPerformance $performance): float
    {
        $nilai = $this->nilaiTerurut($performance);
        $n = $nilai->count();

        if ($n < 2) {
            return 0.0;
        }

        $rata = $nilai->avg();
        $variansi = $nilai->reduce(fn ($carry, $v) => $carry + ($v - $rata) ** 2, 0.0) / $n;

        return round(sqrt($variansi), 4);
    }

    public function selisihKeAcuan(JurusPerformance $performance, ?int $waktuAcuanMs): ?int
    {
        if ($waktuAcuanMs === null || $performance->duration_ms === null) {
            return null;
        }

        return abs($performance->duration_ms - $waktuAcuanMs);
    }

    /**
     * Mengurutkan penampilan dari peringkat tertinggi. Skor sama diselesaikan
     * berurutan lewat `config('scoring.jurus.pemecah_seri')`; kalau semua
     * kriteria terukur masih sama, urutan di antara mereka jatuh ke undian
     * dan TIDAK ditentukan otomatis -- Ketua Pertandingan yang memutus.
     *
     * @param  Collection<int, JurusPerformance>  $performances
     * @param  array<int, int|null>  $waktuAcuanMsPerId  waktu acuan per performance id, untuk pemecah seri "waktu terdekat"
     * @return Collection<int, JurusPerformance>
     */
    public function peringkat(Collection $performances, array $waktuAcuanMsPerId = []): Collection
    {
        return $performances->sort(function (JurusPerformance $a, JurusPerformance $b) use ($waktuAcuanMsPerId) {
            $skorA = $this->skorAkhir($a);
            $skorB = $this->skorAkhir($b);

            if ($skorA !== $skorB) {
                return $skorB <=> $skorA;
            }

            foreach (config('scoring.jurus.pemecah_seri') as $kriteria) {
                $bandingan = match ($kriteria) {
                    'hukuman_terendah' => $this->totalPengurangan($a) <=> $this->totalPengurangan($b),
                    'waktu_terdekat_ke_acuan' => $this->bandingkanSelisihWaktu($a, $b, $waktuAcuanMsPerId),
                    'standar_deviasi_terendah' => $this->standarDeviasi($a) <=> $this->standarDeviasi($b),
                    default => 0, // 'undian' -- tidak bisa dihitung, urutan tetap
                };

                if ($bandingan !== 0) {
                    return $bandingan;
                }
            }

            return 0;
        })->values();
    }

    private function bandingkanSelisihWaktu(JurusPerformance $a, JurusPerformance $b, array $waktuAcuanMsPerId): int
    {
        $selisihA = $this->selisihKeAcuan($a, $waktuAcuanMsPerId[$a->id] ?? null);
        $selisihB = $this->selisihKeAcuan($b, $waktuAcuanMsPerId[$b->id] ?? null);

        if ($selisihA === null || $selisihB === null) {
            return 0;
        }

        return $selisihA <=> $selisihB;
    }

    /** @return Collection<int, float> */
    private function nilaiTerurut(JurusPerformance $performance): Collection
    {
        return $performance->scores->map(fn ($s) => (float) $s->value)->sort()->values();
    }
}
