<?php

namespace App\Support\Var;

use App\Models\ManagerProtest;
use App\Models\SilatMatch;
use RuntimeException;

/** Protes Manajer berjenjang -- Pasal 15 ayat 4. */
class PengajuanProtesManajer
{
    public function pertama(SilatMatch $match, ?string $catatan): ManagerProtest
    {
        if ($match->managerProtests()->where('level', ManagerProtest::TINGKAT_PERTAMA)->exists()) {
            throw new RuntimeException('Partai ini sudah punya protes manajer tingkat pertama.');
        }

        return $this->buat($match, ManagerProtest::TINGKAT_PERTAMA, null, $catatan, 'tingkat_pertama');
    }

    public function banding(ManagerProtest $pertama, ?string $catatan): ManagerProtest
    {
        if ($pertama->level !== ManagerProtest::TINGKAT_PERTAMA) {
            throw new RuntimeException('Banding hanya bisa diajukan dari protes tingkat pertama.');
        }

        if (! $pertama->sudahDiputuskan()) {
            throw new RuntimeException('Banding hanya bisa diajukan setelah tingkat pertama diputuskan.');
        }

        if ($pertama->banding()->exists()) {
            throw new RuntimeException('Protes ini sudah dibanding.');
        }

        return $this->buat($pertama->match, ManagerProtest::BANDING, $pertama->id, $catatan, 'banding');
    }

    private function buat(SilatMatch $match, string $level, ?int $parentId, ?string $catatan, string $configKey): ManagerProtest
    {
        $diajukanAt = now();
        $tenggat = config("scoring.protes_manajer.{$configKey}");

        return ManagerProtest::create([
            'match_id' => $match->id,
            'level' => $level,
            'parent_id' => $parentId,
            'diajukan_at' => $diajukanAt,
            'tenggat_formulir_at' => $diajukanAt->clone()->addMinutes($tenggat['kembalikan_formulir_menit']),
            'tenggat_keputusan_at' => $diajukanAt->clone()->addMinutes($tenggat['keputusan_menit']),
            'catatan' => $catatan,
        ]);
    }
}
