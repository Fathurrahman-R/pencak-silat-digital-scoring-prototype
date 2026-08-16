<?php

namespace App\Support\Var;

use App\Models\User;
use App\Models\VarReview;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Keputusan Wasit Komisi Protes -- Pasal 15. "Tidak Sah" membatalkan nilai
 * atau hukuman yang disengketakan lewat baris pembatal yang sama dipakai
 * koreksi dewan juri (`voided_at`/`voided_by`/`void_reason`), bukan
 * menyunting riwayatnya -- supaya jejak penilaian tetap utuh.
 */
class KeputusanVar
{
    public function __invoke(VarReview $review, string $keputusan, ?string $catatan, User $pemutus): VarReview
    {
        if ($review->sudahDiputuskan()) {
            throw new RuntimeException('Protes ini sudah diputuskan.');
        }

        return DB::transaction(function () use ($review, $keputusan, $catatan, $pemutus) {
            $review->update([
                'keputusan' => $keputusan,
                'diputuskan_at' => now(),
                'diputuskan_oleh' => $pemutus->id,
                'catatan' => $catatan,
            ]);

            if ($keputusan === VarReview::TIDAK_SAH) {
                $alasan = "Dibatalkan lewat VAR #{$review->id}".($catatan ? ": {$catatan}" : '.');

                $review->scoreEvent?->update([
                    'voided_at' => now(), 'voided_by' => $pemutus->id, 'void_reason' => $alasan,
                ]);

                $review->penalty?->update([
                    'voided_at' => now(), 'voided_by' => $pemutus->id, 'void_reason' => $alasan,
                ]);
            }

            return $review;
        });
    }
}
