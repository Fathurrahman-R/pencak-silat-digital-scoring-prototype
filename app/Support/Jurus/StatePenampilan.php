<?php

namespace App\Support\Jurus;

use App\Models\JurusPerformance;

/**
 * Muatan state satu penampilan Jurus.
 *
 * Dipindah keluar dari JurusScoringController begitu panel Jurus punya alamat
 * per GELANGGANG di samping alamat per penampilan: dua controller sekarang
 * menjawab pertanyaan yang sama, dan muatan yang disalin adalah muatan yang
 * suatu saat diperbaiki di satu tempat saja.
 *
 * Pola yang sama dengan App\Support\Live\StatePartaiPublik untuk Tanding.
 */
class StatePenampilan
{
    public function __construct(private readonly JurusScoreCalculator $kalkulator) {}

    /** @return array<string, mixed> */
    public function __invoke(JurusPerformance $performance): array
    {
        $performance->loadMissing([
            'scores.juri',
            'deductions.pencatat',
            'registration.athletes',
            'registration.contingent',
        ]);

        return [
            'performance' => [
                'id' => $performance->id,
                'status' => $performance->status,
                'started_at' => optional($performance->started_at)->toIso8601String(),
                'duration_ms' => $performance->duration_ms,
                'didiskualifikasi' => $performance->didiskualifikasi,
                'ratified' => $performance->disahkan(),
            ],
            'peserta' => [
                'nama' => $performance->registration->athletes->pluck('name')->implode(', '),
                'kontingen' => $performance->registration->contingent->name,
            ],
            'skor' => [
                'median' => $this->kalkulator->median($performance),
                'total_pengurangan' => $this->kalkulator->totalPengurangan($performance),
                'akhir' => $this->kalkulator->skorAkhir($performance),
            ],
            'nilai_juri' => $performance->scores->map(fn ($s) => [
                'judge_user_id' => $s->judge_user_id,
                'nama' => $s->juri->name,
                'value' => (float) $s->value,
            ]),
            'pengurangan' => $performance->deductions->where('voided_at', null)->values()->map(fn ($d) => [
                'id' => $d->id,
                'tier' => $d->tier,
                'alasan' => $d->alasan,
                'jumlah' => (float) $d->jumlah,
                'pencatat' => $d->pencatat?->name,
            ]),
        ];
    }
}
