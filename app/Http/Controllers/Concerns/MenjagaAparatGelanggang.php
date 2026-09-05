<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Penjagaan "apakah orang ini bertugas di gelanggang tempat partai ini
 * dimainkan".
 *
 * Dipindah keluar dari PartaiScoringController begitu panel per-gelanggang
 * lahir: dua controller sekarang membuka panel yang sama, dan penjagaan yang
 * disalin adalah penjagaan yang suatu saat diperbaiki di satu tempat saja.
 */
trait MenjagaAparatGelanggang
{
    /**
     * Wasit dan juri hanya berwenang atas partai yang ditugaskan kepada
     * mereka. Izin peran saja tidak cukup: dua gelanggang berjalan
     * bersamaan dengan aparat yang sama-sama punya izin menilai, dan
     * aparat gelanggang sebelah tidak boleh ikut menilai atau menghukum
     * di sini.
     *
     * Peran tingkat kejuaraan -- Dewan Wasit Juri, Ketua Pertandingan --
     * sengaja tidak ikut aturan ini. Kewenangan mereka memang lintas
     * gelanggang, jadi mereka tidak pernah muncul di match_officials.
     * Yang diperiksa hanya peran yang memang ditugaskan per partai.
     */
    protected function pastikanAparatPartai(SilatMatch $match, ?User $user): void
    {
        if ($user === null) {
            abort(403);
        }

        $peranPerPartai = [
            'juri' => MatchOfficial::ROLE_JURI,
            'wasit' => MatchOfficial::ROLE_WASIT,
        ];

        /*
         * Cukup ditugaskan dalam SALAH SATU kapasitas, bukan setiap kapasitas
         * yang perannya izinkan. Satu akun boleh memegang wasit sekaligus
         * juri; menuntut keduanya akan menolak wasit yang kebetulan juga
         * berperan juri di partai yang justru ditugaskan kepadanya.
         */
        $kapasitas = array_values(array_intersect_key(
            $peranPerPartai,
            array_flip($user->getRoleNames()->all()),
        ));

        if ($kapasitas !== []) {
            abort_unless(
                MatchOfficial::query()
                    ->where('match_id', $match->id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', $kapasitas)
                    ->exists(),
                403,
                'Anda tidak ditugaskan sebagai aparat pada partai ini.',
            );
        }

        /*
         * Operator terikat gelanggang, bukan partai: ia memegang satu
         * gelanggang sepanjang hari, jadi penugasannya ikut berlaku untuk
         * partai yang baru dijadwalkan ke sana kemudian.
         *
         * Partai yang belum punya gelanggang belum bisa dioperasikan
         * siapa pun -- jadwalkan dulu, baru ada operatornya.
         */
        if ($user->hasRole('operator-it')) {
            /*
             * Dua sebab penolakan yang berbeda, dua kalimat yang berbeda.
             * Operator yang membuka partai yang belum dijadwalkan pernah
             * dijawab "Anda bukan operator gelanggang ini" -- kalimat yang
             * menyuruhnya mencari gelanggang yang salah, padahal yang kurang
             * adalah jadwalnya.
             */
            abort_if(
                $match->arena_id === null,
                403,
                'Partai ini belum ditempatkan di gelanggang, jadi belum ada operatornya. Jadwalkan dulu lewat menu Jadwal.',
            );

            abort_unless(
                DB::table('arena_operators')
                    ->where('arena_id', $match->arena_id)
                    ->where('user_id', $user->id)
                    ->exists(),
                403,
                'Anda bukan operator gelanggang tempat partai ini dimainkan.',
            );
        }

        /*
         * Pengendali terikat gelanggang dengan alasan yang sama seperti
         * operator, tapi taruhannya lebih besar: ia yang menjalankan timer dan
         * memindahkan jadwal. Pengendali gelanggang sebelah yang membuka
         * alamat ini bisa menghentikan partai yang bukan urusannya.
         */
        if ($user->hasRole('pengendali-gelanggang')) {
            abort_unless(
                $match->arena_id !== null && DB::table('arena_pengendali')
                    ->where('arena_id', $match->arena_id)
                    ->where('user_id', $user->id)
                    ->exists(),
                403,
                'Anda bukan pengendali gelanggang tempat partai ini dimainkan.',
            );
        }
    }
}
