<?php

namespace App\Support\Scoring;

use App\Models\JudgeInput;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use Illuminate\Support\Facades\DB;

/**
 * Menilai apakah satu input juri baru saja membentuk nilai yang sah.
 *
 * Dipanggil tepat setelah satu JudgeInput disimpan. Ia tidak menjadwalkan
 * dirinya sendiri lewat scheduler latar -- evaluasinya terjadi saat input
 * tiba, persis seperti sistem digital scoring resmi menerbitkan nilai tepat
 * ketika juri ke-N menekan.
 *
 * Ambang dan lebar window dibaca dari setelan peraturan turnamen, bukan
 * angka tetap, karena naskah 2025 tidak mengaturnya -- lihat
 * TournamentRuleSetting dan catatan di config/scoring.php.
 */
class ConsensusEvaluator
{
    public function evaluasi(JudgeInput $input): ?ScoreEvent
    {
        if ($input->ditolak() || $input->sudahDipakai()) {
            return null;
        }

        $peraturan = $input->match->bracket->weightClass->tournament->peraturan();

        return DB::transaction(function () use ($input, $peraturan) {
            // Baris partai dikunci supaya dua input yang tiba nyaris
            // bersamaan tidak sempat lolos evaluasi ganda dan melahirkan dua
            // nilai kembar untuk momen yang sama.
            SilatMatch::whereKey($input->match_id)->lockForUpdate()->first();

            /*
             * Diformat jadi string bermilidetik secara eksplisit -- format
             * tanggal bawaan query builder Laravel ('Y-m-d H:i:s') memangkas
             * milidetik saat mem-bind objek Carbon, dan window 2 detik tidak
             * berarti apa-apa lagi kalau presisinya jatuh ke detik penuh.
             */
            $batasBawah = $input->server_ts->clone()->subMilliseconds($peraturan->window_konsensus_ms)->format('Y-m-d H:i:s.v');
            $batasAtas = $input->server_ts->format('Y-m-d H:i:s.v');

            $kandidat = JudgeInput::query()
                ->where('match_id', $input->match_id)
                ->where('round', $input->round)
                ->where('corner', $input->corner)
                ->where('point_type', $input->point_type)
                ->whereNull('score_event_id')
                ->whereNull('rejected_reason')
                ->whereBetween('server_ts', [$batasBawah, $batasAtas])
                ->lockForUpdate()
                ->get();

            $juriBerbeda = $kandidat->pluck('judge_user_id')->unique();

            if ($juriBerbeda->count() < $peraturan->ambang_sepakat) {
                return null;
            }

            $scoreEvent = ScoreEvent::create([
                'match_id' => $input->match_id,
                'round' => $input->round,
                'corner' => $input->corner,
                'point_type' => $input->point_type,
                'value' => $input->point_type->nilai(),
                'server_ts' => $input->server_ts,
            ]);

            JudgeInput::whereIn('id', $kandidat->pluck('id'))->update(['score_event_id' => $scoreEvent->id]);

            return $scoreEvent;
        });
    }
}
