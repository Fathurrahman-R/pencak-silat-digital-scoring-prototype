<?php

namespace App\Support\Var;

use App\Enums\Sudut;
use App\Models\Penalty;
use App\Models\ProtestCard;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\User;
use App\Models\VarReview;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pengajuan protes VAR -- Pasal 15.2.a. Kartu dihitung per sudut per
 * partai, bukan per babak, karena naskah menyebut jatahnya "berlaku
 * sepanjang tiga babak".
 */
class PengajuanProtes
{
    public function __invoke(
        SilatMatch $match,
        Sudut $sudut,
        int $babak,
        string $kejadian,
        User $pengaju,
        ?ScoreEvent $scoreEvent = null,
        ?Penalty $penalty = null,
    ): VarReview {
        return DB::transaction(function () use ($match, $sudut, $babak, $kejadian, $pengaju, $scoreEvent, $penalty) {
            $kartu = ProtestCard::lockForUpdate()->firstOrCreate(
                ['match_id' => $match->id, 'corner' => $sudut],
                ['jumlah_dipakai' => 0],
            );

            $jatah = config('scoring.var.kartu_protes.tanding');

            if ($kartu->jumlah_dipakai >= $jatah) {
                throw new RuntimeException('Kartu protes sudut ini sudah habis.');
            }

            $kartu->increment('jumlah_dipakai');

            $diajukanAt = now();

            return VarReview::create([
                'match_id' => $match->id,
                'protest_card_id' => $kartu->id,
                'round' => $babak,
                'corner' => $sudut,
                'kejadian' => $kejadian,
                'score_event_id' => $scoreEvent?->id,
                'penalty_id' => $penalty?->id,
                'diajukan_at' => $diajukanAt,
                'diajukan_oleh' => $pengaju->id,
                'tenggat_at' => $diajukanAt->clone()->addSeconds(config('scoring.var.tenggat_keputusan_detik')),
            ]);
        });
    }
}
