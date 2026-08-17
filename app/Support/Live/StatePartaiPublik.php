<?php

namespace App\Support\Live;

use App\Enums\Sudut;
use App\Models\Arena;
use App\Models\SilatMatch;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;

/**
 * Payload skor publik satu gelanggang -- dipakai overlay siaran (Fase 6,
 * dikunci jaringan lokal) dan live score publik (Fase 5, dibuka lewat
 * tunnel). Keduanya butuh bentuk data yang identik: tanpa `officials`,
 * tanpa `judge_user_id`, tanpa input mentah juri -- itu urusan panel admin,
 * bukan tontonan.
 *
 * Awalnya hidup di App\Http\Controllers\OverlayController; dipindah ke sini
 * begitu LiveScoreController butuh bentuk yang sama persis, supaya kedua
 * pemakainya tidak diam-diam bergeser satu sama lain.
 */
class StatePartaiPublik
{
    public function __construct(
        private readonly TandingScoreCalculator $kalkulator,
        private readonly TanggaHukuman $tangga,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Arena $arena): array
    {
        $match = $this->partaiRelevan($arena);

        if ($match === null) {
            return ['ada_partai' => false];
        }

        $babakSekarang = $match->current_round ?? $match->rounds->max('round') ?? 1;

        $penalti = fn (Sudut $sudut) => [
            'pembinaan' => $this->tangga->jumlahPembinaan($match, $sudut),
            'teguran' => $this->tangga->jumlahTeguran($match, $sudut, $babakSekarang),
            'peringatan' => $this->tangga->jumlahPeringatan($match, $sudut),
        ];

        $round = $match->rounds->firstWhere('round', $babakSekarang);

        return [
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
