<?php

namespace App\Support\Var;

use App\Enums\AkibatProtes;
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
    /**
     * @param  AkibatProtes|null  $akibat  wajib bila protesnya DITERIMA --
     *                                     Pasal 15 ayat 4 huruf c.e menyediakan
     *                                     tiga bentuk jawaban, dan tidak satu pun
     *                                     di antaranya berbunyi "diterima tanpa
     *                                     akibat"
     *
     * @throws RuntimeException
     */
    public function __invoke(
        ManagerProtest $protest,
        string $keputusan,
        ?string $catatan,
        User $pemutus,
        ?AkibatProtes $akibat = null,
    ): ManagerProtest {
        if ($protest->sudahDiputuskan()) {
            throw new RuntimeException('Protes ini sudah diputuskan.');
        }

        if ($keputusan === 'diterima' && $akibat === null) {
            throw new RuntimeException(
                'Protes yang diterima harus menyebut akibatnya: mengubah hasil, menambah satu babak, atau penampilan kembali.',
            );
        }

        $protest->update([
            'keputusan' => $keputusan,
            'akibat' => $keputusan === 'diterima' ? $akibat?->value : null,
            'diputuskan_at' => now(),
            'diputuskan_oleh' => $pemutus->id,
            'catatan' => $catatan,
        ]);

        return $protest;
    }
}
