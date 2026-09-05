<?php

namespace App\Support\Scoring;

use App\Enums\StatusBabak;
use App\Models\MatchRound;
use App\Models\MatchRoundReopen;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membuka kembali satu babak yang sudah ditutup, untuk nilai atau hukuman yang
 * terlewat dicatat.
 *
 * # Kenapa bukan menurunkan current_round
 *
 * Menurunkannya berarti babak berikutnya kehilangan statusnya sementara skor
 * yang sudah terbit di sana tetap ada -- angka yang menggantung tanpa babak
 * yang memilikinya. Sebagai gantinya babak lama DIBUKA sementara babak berjalan
 * dijeda; `current_round` tidak pernah bergerak mundur.
 *
 * MatchTimer tetap satu-satunya pemilik transisi babak dan penulis tunggal
 * `current_round`. Kelas ini MEMANGGILNYA, tidak menirunya.
 *
 * # Kenapa timernya tidak dijalankan
 *
 * Babak susulan tidak menghitung waktu. Yang dicatat adalah kejadian yang
 * sudah terjadi menit-menit lalu; menyalakan jamnya berarti mengarang durasi
 * yang tidak pernah ada, dan durasi itu ikut masuk berita acara.
 */
class BabakSusulan
{
    public function __construct(private readonly MatchTimer $timer) {}

    /**
     * @throws RuntimeException
     */
    public function buka(SilatMatch $match, int $babak, User $oleh): SilatMatch
    {
        if ($match->susulan_round !== null) {
            throw new RuntimeException("Babak {$match->susulan_round} sedang dibuka untuk input susulan.");
        }

        if ($match->disahkan()) {
            throw new RuntimeException('Hasil partai ini sudah disahkan, jadi babaknya tidak bisa dibuka lagi.');
        }

        if ($babak >= ($match->current_round ?? 0)) {
            throw new RuntimeException(
                'Hanya babak yang sudah lewat yang bisa dibuka. Babak berjalan diperbaiki lewat panelnya sendiri.',
            );
        }

        $round = $match->rounds()->where('round', $babak)->first();

        if ($round === null || $round->status !== StatusBabak::Selesai) {
            throw new RuntimeException("Babak {$babak} belum pernah diselesaikan.");
        }

        return DB::transaction(function () use ($match, $babak, $oleh) {
            /*
             * Babak berjalan dijeda lebih dulu. Kalau tidak, juri bisa menekan
             * nilai untuk babak berjalan sementara pengendali mengira seluruh
             * gelanggang sedang berhenti mencatat susulan.
             */
            $aktif = $match->babakAktif();
            $jedaOtomatis = false;

            if ($aktif?->berjalan()) {
                $this->timer->jeda($aktif);
                $jedaOtomatis = true;
            }

            $match->update([
                'susulan_round' => $babak,
                'susulan_dibuka_at' => now(),
                'susulan_dibuka_oleh' => $oleh->id,
                'susulan_jeda_otomatis' => $jedaOtomatis,
            ]);

            MatchRoundReopen::create([
                'match_id' => $match->id,
                'round' => $babak,
                'opened_by' => $oleh->id,
                'opened_at' => now(),
            ]);

            return $match->refresh();
        });
    }

    /**
     * @throws RuntimeException
     */
    public function tutup(SilatMatch $match, User $oleh): SilatMatch
    {
        if ($match->susulan_round === null) {
            throw new RuntimeException('Tidak ada babak susulan yang terbuka.');
        }

        return DB::transaction(function () use ($match, $oleh) {
            MatchRoundReopen::where('match_id', $match->id)
                ->where('round', $match->susulan_round)
                ->whereNull('closed_at')
                ->latest('id')
                ->first()
                ?->update(['closed_by' => $oleh->id, 'closed_at' => now()]);

            $jedaOtomatis = $match->susulan_jeda_otomatis;

            $match->update([
                'susulan_round' => null,
                'susulan_dibuka_at' => null,
                'susulan_dibuka_oleh' => null,
                'susulan_jeda_otomatis' => false,
            ]);

            /*
             * Hanya dilanjutkan kalau KAMI yang menjedanya. Babak yang sudah
             * dijeda pengendali sebelumnya tetap jeda, dan panel menyebutkannya
             * eksplisit -- babak yang tiba-tiba berjalan lagi tanpa ada yang
             * menekan adalah kejutan yang mahal di gelanggang.
             */
            $aktif = $match->refresh()->babakAktif();

            if ($jedaOtomatis && $aktif?->status === StatusBabak::Jeda) {
                $this->timer->lanjutkan($aktif);
            }

            return $match->refresh();
        });
    }

    /** Babak yang sedang dibuka, kalau ada. */
    public function terbuka(SilatMatch $match): ?MatchRound
    {
        return $match->babakSusulan();
    }
}
