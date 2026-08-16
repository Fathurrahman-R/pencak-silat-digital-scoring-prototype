<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;
use App\Models\SilatMatch;
use App\Models\TechnicalCount;
use App\Models\User;
use RuntimeException;

/**
 * Hitungan wasit terhadap pesilat yang jatuh -- Pasal 11.6.g.2 dan 11.6.g.3.
 *
 * Tiga akibat yang bisa terjadi dari satu hitungan, dan ketiganya bisa
 * bertumpuk pada hitungan yang sama: hitungan ke-9 menjatuhkan Teguran I,
 * hitungan ke-10 langsung mengakhiri partai (menang mutlak), dan hitungan
 * ketiga berturut-turut terhadap sudut yang sama dalam satu babak membuat
 * lawannya menang teknik.
 */
class HitunganTeknik
{
    public function __construct(
        private readonly TanggaHukuman $tangga,
        private readonly MatchTimer $timer,
    ) {}

    public function catat(SilatMatch $match, Sudut $sudut, int $babak, int $hitunganTertinggi, User $pencatat): TechnicalCount
    {
        if ($hitunganTertinggi < 1 || $hitunganTertinggi > 10) {
            throw new RuntimeException('Hitungan harus antara 1 dan 10.');
        }

        $hitungan = TechnicalCount::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'count_reached' => $hitunganTertinggi,
            'created_by' => $pencatat->id,
        ]);

        $ambangTeguran = config('scoring.tanding.hitungan_teknik.teguran_pada_hitungan');
        $ambangMutlak = config('scoring.tanding.hitungan_teknik.mutlak_pada_hitungan');
        $beruntunMenang = config('scoring.tanding.hitungan_teknik.menang_teknik_setelah_hitungan_beruntun');

        if ($hitunganTertinggi >= $ambangTeguran && ! $this->tangga->sudahDiskualifikasi($match, $sudut)) {
            $this->tangga->catatLangsungTeguran(
                $match, $sudut, $babak, "Hitungan teknik mencapai {$hitunganTertinggi}.", $pencatat,
            );
        }

        if ($hitunganTertinggi >= $ambangMutlak) {
            $this->timer->akhiriPartai($match, $this->lawan($match, $sudut), 'mutlak');

            return $hitungan->refresh();
        }

        if ($this->hitunganBeruntun($match, $sudut, $babak) >= $beruntunMenang) {
            $this->timer->akhiriPartai($match, $this->lawan($match, $sudut), 'teknik');
        }

        return $hitungan->refresh();
    }

    /**
     * Berapa kali beruntun sudut ini dihitung dalam babak ini, tanpa
     * diselingi hitungan terhadap sudut lawan.
     */
    private function hitunganBeruntun(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        $beruntun = 0;

        foreach ($match->technicalCounts()->where('round', $babak)->orderByDesc('id')->get() as $h) {
            if ($h->corner !== $sudut) {
                break;
            }

            $beruntun++;
        }

        return $beruntun;
    }

    private function lawan(SilatMatch $match, Sudut $sudut)
    {
        return $sudut === Sudut::Merah ? $match->blue : $match->red;
    }
}
