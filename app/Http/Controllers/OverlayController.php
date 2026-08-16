<?php

namespace App\Http\Controllers;

use App\Enums\Sudut;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Scoring\TanggaHukuman;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Overlay siaran vMix -- lima halaman tanpa elemen interaktif, dipasang
 * sebagai Web Browser Input. Tidak ada middleware 'auth' di rute manapun
 * yang mengarah ke sini; satu-satunya pengamannya AllowLocalNetworkOnly.
 *
 * Payload state() sengaja lebih tipis dari PartaiScoringController::state():
 * tidak ada `officials` maupun `riwayat` beralasan pembatalan -- itu urusan
 * panel admin, bukan tontonan.
 */
class OverlayController extends Controller
{
    public function __construct(
        private readonly TandingScoreCalculator $kalkulator,
        private readonly TanggaHukuman $tangga,
    ) {}

    public function state(Arena $arena): JsonResponse
    {
        $match = $this->partaiRelevan($arena);

        if ($match === null) {
            return response()->json(['ada_partai' => false]);
        }

        $babakSekarang = $match->current_round ?? $match->rounds->max('round') ?? 1;

        $penalti = fn (Sudut $sudut) => [
            'pembinaan' => $this->tangga->jumlahPembinaan($match, $sudut),
            'teguran' => $this->tangga->jumlahTeguran($match, $sudut, $babakSekarang),
            'peringatan' => $this->tangga->jumlahPeringatan($match, $sudut),
        ];

        $round = $match->rounds->firstWhere('round', $babakSekarang);

        return response()->json([
            'ada_partai' => true,
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'current_round' => $match->current_round,
                'win_reason' => $match->win_reason,
                'winner_corner' => $match->winner_registration_id === null ? null
                    : ($match->winner_registration_id === $match->red_registration_id ? 'red' : 'blue'),
                'ratified' => $match->disahkan(),
            ],
            'kelas' => [
                'nama' => $match->bracket->weightClass->name,
                'golongan' => $match->bracket->weightClass->golongan_usia->label(),
                'jenis_kelamin' => $match->bracket->weightClass->jenis_kelamin->label(),
            ],
            'babak_label' => $match->bracket->namaBabak($match->round),
            'red' => $match->red ? [
                'nama' => $match->red->athletes->pluck('name')->implode(', '),
                'kontingen' => $match->red->contingent->name,
            ] : null,
            'blue' => $match->blue ? [
                'nama' => $match->blue->athletes->pluck('name')->implode(', '),
                'kontingen' => $match->blue->contingent->name,
            ] : null,
            'timer' => $round ? [
                'round' => $round->round,
                'status' => $round->status->value,
                'duration_ms' => $round->duration_ms,
                'accumulated_ms' => $round->accumulated_ms,
                'started_at' => optional($round->started_at)->toIso8601String(),
            ] : null,
            'skor_total' => [
                'merah' => $this->kalkulator->skor($match, Sudut::Merah),
                'biru' => $this->kalkulator->skor($match, Sudut::Biru),
            ],
            'hukuman' => [
                'merah' => $penalti(Sudut::Merah),
                'biru' => $penalti(Sudut::Biru),
            ],
        ]);
    }

    public function scorebug(Arena $arena): View
    {
        return view('overlay.scorebug', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    public function athlete(Arena $arena, string $corner): View
    {
        abort_unless(in_array($corner, ['red', 'blue'], true), 404);

        return view('overlay.athlete', [
            'arena' => $arena,
            'corner' => $corner,
            'config' => $this->config($arena),
        ]);
    }

    public function breakdown(Arena $arena): View
    {
        return view('overlay.breakdown', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    public function result(Arena $arena): View
    {
        return view('overlay.result', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    /**
     * Bagan untuk tayangan antar partai. Kelasnya dipilih lewat ?kelas=ID
     * karena satu turnamen bisa punya ratusan kelas -- operator vMix
     * menyetel URL sekali saat mengarahkan sumber ke kelas yang mau
     * ditayangkan, bukan menebak dari partai yang sedang aktif.
     *
     * Rekap medali penuh menyusul Fase 8; belum ada mesin hitungnya di
     * codebase ini.
     */
    public function bracket(Request $request, Tournament $tournament): View
    {
        $weightClassId = $request->integer('kelas');
        $weightClass = $weightClassId
            ? WeightClass::where('tournament_id', $tournament->id)->find($weightClassId)
            : null;

        $bracket = $weightClass
            ? Bracket::where('weight_class_id', $weightClass->id)->with([
                'weightClass',
                'matches.red.athletes', 'matches.red.contingent',
                'matches.blue.athletes', 'matches.blue.contingent',
            ])->first()
            : null;

        return view('overlay.bracket', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'babak' => $bracket?->matches->groupBy('round'),
        ]);
    }

    /** @return array<string, mixed> */
    private function config(Arena $arena): array
    {
        return [
            'arenaId' => $arena->id,
            'state' => route('overlay.state', $arena),
        ];
    }

    /** Partai yang sedang berlangsung di gelanggang ini, atau partai terakhir yang selesai kalau belum ada yang berlangsung. */
    private function partaiRelevan(Arena $arena): ?SilatMatch
    {
        $muatan = [
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            'bracket.weightClass', 'rounds',
        ];

        return SilatMatch::where('arena_id', $arena->id)
                ->where('status', SilatMatch::STATUS_BERLANGSUNG)
                ->with($muatan)
                ->first()
            ?? SilatMatch::where('arena_id', $arena->id)
                ->where('status', SilatMatch::STATUS_SELESAI)
                ->with($muatan)
                ->latest('updated_at')
                ->first();
    }
}
