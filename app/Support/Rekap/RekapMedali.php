<?php

namespace App\Support\Rekap;

use App\Models\Bracket;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Jurus\JurusScoreCalculator;
use Illuminate\Support\Collection;

/**
 * Rekap medali -- FR-J-01, FR-J-02.
 *
 * Kategori Tanding: emas dan perak dari partai final, perunggu dari KEDUA
 * partai semifinal (naskah pencak silat tidak mengenal playoff perebutan
 * juara tiga -- pecundang kedua semifinalis sama-sama pulang dengan
 * perunggu). Bagan berukuran 2 (langsung final) tidak punya semifinal,
 * jadi tidak ada perunggu untuk kelas itu -- bukan bug, memang begitu
 * aturannya.
 *
 * Kategori Jurus: tiga peringkat teratas dari `JurusScoreCalculator::peringkat()`,
 * hanya dari penampilan yang sudah disahkan (`ratified_at` terisi) --
 * skor yang belum disahkan masih bisa dikoreksi dewan juri, jadi belum
 * pantas dianggap final untuk rekap.
 */
class RekapMedali
{
    public function __construct(private readonly JurusScoreCalculator $jurusKalkulator) {}

    /** @return Collection<int, array{kelas: WeightClass, emas: ?Registration, perak: ?Registration, perunggu: Collection<int, Registration>}> */
    public function tanding(Tournament $tournament): Collection
    {
        return $tournament->weightClasses()
            ->with(['bracket.matches.red.athletes', 'bracket.matches.red.contingent', 'bracket.matches.blue.athletes', 'bracket.matches.blue.contingent'])
            ->orderBy('golongan_usia')->orderBy('jenis_kelamin')->orderBy('code')
            ->get()
            ->map(fn (WeightClass $kelas) => $this->tandingSatuKelas($kelas))
            ->filter()
            ->values();
    }

    /** @return array{kelas: WeightClass, emas: ?Registration, perak: ?Registration, perunggu: Collection<int, Registration>}|null */
    private function tandingSatuKelas(WeightClass $kelas): ?array
    {
        $bracket = $kelas->bracket;

        if ($bracket === null || $bracket->matches->isEmpty()) {
            return null;
        }

        $babakFinal = $bracket->matches->max('round');
        $final = $bracket->matches->firstWhere('round', $babakFinal);

        if ($final === null || ! $final->disahkan() || $final->winner_registration_id === null) {
            return null;
        }

        $emas = $final->winner_registration_id === $final->red_registration_id ? $final->red : $final->blue;
        $perak = $final->winner_registration_id === $final->red_registration_id ? $final->blue : $final->red;

        $perunggu = $bracket->matches->where('round', $babakFinal - 1)
            ->map(function ($partai) {
                if ($partai->winner_registration_id === null) {
                    return null;
                }

                return $partai->winner_registration_id === $partai->red_registration_id ? $partai->blue : $partai->red;
            })
            ->filter()
            ->values();

        return ['kelas' => $kelas, 'emas' => $emas, 'perak' => $perak, 'perunggu' => $perunggu];
    }

    /** @return Collection<int, array{nomor: JurusEvent, emas: ?Registration, perak: ?Registration, perunggu: ?Registration}> */
    public function jurus(Tournament $tournament): Collection
    {
        return $tournament->jurusEvents()->aktif()
            ->with(['performances' => fn ($q) => $q->whereNotNull('ratified_at')->with('registration.athletes', 'registration.contingent', 'scores', 'deductions')])
            ->orderBy('golongan_usia')->orderBy('sort_order')
            ->get()
            ->map(fn (JurusEvent $nomor) => $this->jurusSatuNomor($nomor))
            ->filter()
            ->values();
    }

    /** @return array{nomor: JurusEvent, emas: ?Registration, perak: ?Registration, perunggu: ?Registration}|null */
    private function jurusSatuNomor(JurusEvent $nomor): ?array
    {
        if ($nomor->performances->isEmpty()) {
            return null;
        }

        $peringkat = $this->jurusKalkulator->peringkat($nomor->performances);

        return [
            'nomor' => $nomor,
            'emas' => $peringkat->get(0)?->registration,
            'perak' => $peringkat->get(1)?->registration,
            'perunggu' => $peringkat->get(2)?->registration,
        ];
    }

    /**
     * Peringkat umum per kontingen -- diurutkan emas terbanyak, lalu perak,
     * lalu perunggu (konvensi lazim papan medali, bukan diatur naskah).
     *
     * @return Collection<int, array{kontingen: string, emas: int, perak: int, perunggu: int}>
     */
    public function peringkatUmum(Tournament $tournament): Collection
    {
        $hitung = [];

        $tambah = function (?Registration $r, string $jenis) use (&$hitung) {
            if ($r === null) {
                return;
            }

            $nama = $r->contingent->name;
            $hitung[$nama] ??= ['kontingen' => $nama, 'emas' => 0, 'perak' => 0, 'perunggu' => 0];
            $hitung[$nama][$jenis]++;
        };

        foreach ($this->tanding($tournament) as $baris) {
            $tambah($baris['emas'], 'emas');
            $tambah($baris['perak'], 'perak');
            foreach ($baris['perunggu'] as $r) {
                $tambah($r, 'perunggu');
            }
        }

        foreach ($this->jurus($tournament) as $baris) {
            $tambah($baris['emas'], 'emas');
            $tambah($baris['perak'], 'perak');
            $tambah($baris['perunggu'], 'perunggu');
        }

        return collect($hitung)->values()
            ->sortByDesc(fn ($b) => $b['emas'] * 1_000_000 + $b['perak'] * 1_000 + $b['perunggu'])
            ->values();
    }
}
