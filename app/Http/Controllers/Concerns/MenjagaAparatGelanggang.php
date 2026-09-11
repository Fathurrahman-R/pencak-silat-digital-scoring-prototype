<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ArenaOfficial;
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
     * Penjagaan "apakah orang ini bertugas di gelanggang ini".
     *
     * # Yang berubah, September 2026
     *
     * Dulu wasit dan juri dijaga PER PARTAI: harus tercatat di
     * `match_officials` partai yang sedang dibuka. Penjagaan itu dilepas
     * bersama pindahnya penugasan aparat ke gelanggang -- panitia yang
     * dilayani aplikasi ini menugaskan sekali di awal hari dan tidak ingin
     * dihadang lagi sesudahnya, dan membuka panel bukan tindakan yang mengubah
     * apa pun.
     *
     * Yang TIDAK ikut dilonggarkan, dan sengaja: siapa yang boleh MENGIRIM
     * nilai tetap ditentukan `match_officials`, karena dari sanalah nomor juri
     * pada tiap nilai masuk dibaca (JudgeInputReceived::nomorJuri) dan ke
     * sanalah berita acara menunjuk. Nilai dari orang yang tidak tercatat
     * sebagai juri partai itu tidak punya nomor, dan yang tidak punya nomor
     * tidak bisa dihitung mediannya.
     *
     * Operator dan pengendali tetap terikat gelanggangnya. Keduanya bukan
     * "membuka layar" melainkan memegang timer dan papan tayang, dan yang
     * memegangnya dari matras sebelah bisa menghentikan partai yang bukan
     * urusannya.
     */
    protected function pastikanAparatPartai(SilatMatch $match, ?User $user): void
    {
        if ($user === null) {
            abort(403);
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

    /**
     * Penjagaan penulis SKOR partai: nilai juri, jatuhan, hukuman, hitungan.
     *
     * Membuka panel bebas -- membuka tidak mengubah apa pun, dan panitia yang
     * dilayani aplikasi ini tidak mau dihadang saat mencari layar. MENULIS ke
     * partai lain soalnya:
     *
     *   - nilai tanpa nomor juri tidak bisa dihitung. Median dan konsensus
     *     membandingkan juri 1, 2, dan 3, dan `JudgeInputReceived::nomorJuri()`
     *     membaca nomor itu dari `match_officials`. Yang tidak tercatat di sana
     *     mengirim nilai yang mendarat tanpa nomor lalu diam-diam tidak ikut
     *     dihitung -- jatuh diam, dan itu lebih buruk daripada ditolak dengan
     *     kalimat;
     *   - hukuman, jatuhan, dan hitungan teknik mengubah angka yang sedang
     *     tayang di layar besar. Yang menekannya dari matras sebelah tidak
     *     selalu membaca pesan galat, tapi penontonnya membaca papan skor.
     *
     * Penugasan gelanggang disalin ke `match_officials` begitu pengendali
     * menunjuk partainya, jadi orang yang memang duduk di kursi gelanggang itu
     * tidak pernah menemui penolakan ini. Yang menemuinya adalah orang yang
     * salah membuka alamat.
     */
    protected function pastikanTercatatDiPartai(SilatMatch $match, ?User $user, string $peranPartai): void
    {
        abort_if($user === null, 403);

        /*
         * Nilai juri menuntut kursi JURI, sisanya cukup kursi apa pun.
         *
         * Nomor juri dibaca dari baris ROLE_JURI, jadi nilai dari kursi lain
         * mendarat tanpa nomor. Hukuman, jatuhan, dan hitungan tidak bernomor
         * -- yang penting orangnya memang bertugas di partai itu, entah
         * sebagai wasit, dewan, atau ketua yang sedang menambal.
         */
        $tercatat = MatchOfficial::query()
            ->where('match_id', $match->id)
            ->where('user_id', $user->id)
            ->when($peranPartai === MatchOfficial::ROLE_JURI, fn ($q) => $q->where('role', $peranPartai))
            ->exists();

        /*
         * Kursi GELANGGANG ikut dihitung, dan itu bukan kelonggaran tambahan:
         * penugasan memang hidup di sana sekarang, dan salinannya ke
         * `match_officials` baru lahir saat pengendali menunjuk partainya.
         * Tanpa ini, aparat yang sah ditolak di partai yang belum pernah
         * ditayangkan -- keadaan tiap partai pertama setiap pagi.
         */
        $pemegangKursi = $tercatat || ($match->arena_id !== null && ArenaOfficial::query()
            ->where('arena_id', $match->arena_id)
            ->where('user_id', $user->id)
            ->when($peranPartai === MatchOfficial::ROLE_JURI, fn ($q) => $q->where('role', $peranPartai))
            ->exists());

        $sebutan = $peranPartai === MatchOfficial::ROLE_JURI ? 'juri' : 'aparat';

        abort_unless(
            $pemegangKursi,
            403,
            "Anda tidak tercatat sebagai {$sebutan} partai ini. "
            .'Minta pengendali menugaskan Anda di kursi gelanggang tempat partai ini dimainkan.',
        );
    }
}
