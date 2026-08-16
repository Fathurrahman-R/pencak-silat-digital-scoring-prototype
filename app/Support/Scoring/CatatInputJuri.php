<?php

namespace App\Support\Scoring;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Events\Scoring\JudgeInputReceived;
use App\Events\Scoring\ScoreAwarded;
use App\Models\JudgeInput;
use App\Models\SilatMatch;
use App\Models\User;

/**
 * Menerima satu tekanan tombol juri lewat HTTP dan menjalankannya lewat
 * ConsensusEvaluator.
 *
 * Baris mentahnya selalu tersimpan apa pun hasilnya (FR-F-04) -- yang
 * membedakan diterima atau ditolak hanyalah `rejected_reason`. Input yang
 * tiba saat babak yang dimaksud bukan babak yang sedang berjalan ditolak di
 * sini, sebelum sempat menyentuh ConsensusEvaluator sama sekali (FR-F-07).
 */
class CatatInputJuri
{
    public function __construct(private readonly ConsensusEvaluator $evaluator) {}

    public function __invoke(
        SilatMatch $match,
        User $juri,
        int $babak,
        Sudut $sudut,
        JenisSerangan $jenis,
        ?string $clientTs = null,
    ): JudgeInput {
        $babakDimaksud = $match->rounds()->where('round', $babak)->first();
        $babakSaatIni = $match->current_round === $babak;
        $berjalan = $babakDimaksud?->berjalan() ?? false;

        $ditolak = ! ($babakSaatIni && $berjalan);

        $input = JudgeInput::create([
            'match_id' => $match->id,
            'round' => $babak,
            'judge_user_id' => $juri->id,
            'corner' => $sudut,
            'point_type' => $jenis,
            'server_ts' => now(),
            'client_ts' => $clientTs,
            'rejected_reason' => $ditolak ? $this->alasanTolak($babakSaatIni, $berjalan) : null,
        ]);

        JudgeInputReceived::dispatch($input);

        if (! $ditolak) {
            $scoreEvent = $this->evaluator->evaluasi($input);

            if ($scoreEvent !== null) {
                ScoreAwarded::dispatch($scoreEvent);
            }
        }

        return $input->refresh();
    }

    private function alasanTolak(bool $babakSaatIni, bool $berjalan): string
    {
        return match (true) {
            ! $babakSaatIni => 'Babak ini bukan babak yang sedang berjalan.',
            ! $berjalan => 'Timer babak sedang tidak berjalan.',
            default => 'Ditolak.',
        };
    }
}
