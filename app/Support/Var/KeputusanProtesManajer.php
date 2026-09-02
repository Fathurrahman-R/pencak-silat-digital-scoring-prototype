<?php

namespace App\Support\Var;

use App\Models\ManagerProtest;
use App\Models\User;
use RuntimeException;

/**
 * Keputusan Protes Manajer -- dua tingkat, keduanya diputus pemegang
 * `protes-manajer => setujui`, yaitu Ketua Pertandingan sejak peran Delegasi
 * Teknik dilebur ke sana. Keputusan banding bersifat final; lihat
 * `ManagerProtest::final()`.
 */
class KeputusanProtesManajer
{
    public function __invoke(ManagerProtest $protest, string $keputusan, ?string $catatan, User $pemutus): ManagerProtest
    {
        if ($protest->sudahDiputuskan()) {
            throw new RuntimeException('Protes ini sudah diputuskan.');
        }

        $protest->update([
            'keputusan' => $keputusan,
            'diputuskan_at' => now(),
            'diputuskan_oleh' => $pemutus->id,
            'catatan' => $catatan,
        ]);

        return $protest;
    }
}
