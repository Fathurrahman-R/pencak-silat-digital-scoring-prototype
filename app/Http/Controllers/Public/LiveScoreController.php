<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Live\StatePartaiPublik;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Live score publik -- FR-H. Dibuka tanpa login lewat tunnel ke internet,
 * jadi payloadnya memakai App\Support\Live\StatePartaiPublik yang sama
 * dipakai overlay siaran: tanpa `officials`, tanpa input mentah juri.
 *
 * Beda dari overlay, halaman di sini punya rangka visual (header, footer,
 * navigasi) karena memang ditonton langsung, bukan dikomposit vMix di atas
 * kamera -- dan endpoint state()-nya dicache sebentar supaya lonjakan
 * penonton yang menyambung ulang bersamaan (lihat FR-H-05) tidak memukul
 * database tiap milidetik.
 */
class LiveScoreController extends Controller
{
    public function __construct(private readonly StatePartaiPublik $state) {}

    public function gelanggang(Arena $arena): View
    {
        return view('public.live.gelanggang', [
            'arena' => $arena->load('tournament'),
            'config' => [
                'arenaId' => $arena->id,
                'state' => route('live.gelanggang.state', $arena),
            ],
        ]);
    }

    public function state(Arena $arena): JsonResponse
    {
        $data = Cache::remember("live-state-arena-{$arena->id}", 1, fn () => ($this->state)($arena));

        return response()->json($data);
    }

    public function turnamen(Tournament $tournament): View
    {
        $arenas = $tournament->arenas()->orderBy('name')->get();

        $kelas = $tournament->weightClasses()
            ->with(['bracket' => fn ($q) => $q->with('matches.winner.athletes')])
            ->orderBy('golongan_usia')->orderBy('jenis_kelamin')->orderBy('code')
            ->get()
            ->map(function (WeightClass $wc) {
                $final = $wc->bracket?->matches->sortByDesc('round')->first();
                $juara = $final && $final->disahkan() ? $final->winner?->athletes->pluck('name')->implode(', ') : null;

                return ['kelas' => $wc, 'punya_bagan' => $wc->bracket !== null, 'juara' => $juara];
            });

        return view('public.live.turnamen', [
            'tournament' => $tournament,
            'arenas' => $arenas,
            'kelas' => $kelas,
        ]);
    }

    /**
     * Bagan satu kelas untuk dilihat publik. Rekap medali gabungan seluruh
     * kelas menyusul Fase 8 -- belum ada mesin hitungnya di codebase ini.
     */
    public function bagan(Tournament $tournament, WeightClass $weightClass): View
    {
        abort_unless($weightClass->tournament_id === $tournament->id, 404);

        $bracket = Bracket::where('weight_class_id', $weightClass->id)->with([
            'matches.red.athletes', 'matches.red.contingent',
            'matches.blue.athletes', 'matches.blue.contingent',
        ])->first();

        return view('public.live.bagan', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'babak' => $bracket?->matches->groupBy('round'),
        ]);
    }
}
