<?php

namespace App\Support\Jurus;

use App\Models\JurusPerformance;
use RuntimeException;

/**
 * Timer penampilan Jurus -- jauh lebih sederhana dari MatchTimer milik
 * Tanding, karena satu penampilan berjalan sekali dari awal sampai selesai
 * tanpa jeda maupun babak. Waktu resmi tetap milik server: `duration_ms`
 * dihitung dari `started_at` server, bukan dari input klien mana pun.
 */
class JurusTimer
{
    public function mulai(JurusPerformance $performance): JurusPerformance
    {
        if ($performance->status === JurusPerformance::STATUS_BERLANGSUNG) {
            throw new RuntimeException('Penampilan ini sudah berjalan.');
        }

        if ($performance->selesai()) {
            throw new RuntimeException('Penampilan ini sudah selesai.');
        }

        $performance->update(['status' => JurusPerformance::STATUS_BERLANGSUNG, 'started_at' => now()]);

        return $performance;
    }

    public function berhenti(JurusPerformance $performance): JurusPerformance
    {
        if ($performance->status !== JurusPerformance::STATUS_BERLANGSUNG) {
            throw new RuntimeException('Penampilan ini belum berjalan.');
        }

        // Carbon::diffInMilliseconds() tidak konsisten arah tandanya antar versi
        // -- dihitung langsung dari selisih epoch milidetik supaya tidak ambigu.
        // Dipakai valueOf() (bukan microtime()) supaya tetap tunduk pada
        // Carbon::setTestNow(), yang dipakai test lewat travel()/travelTo().
        $durasiMs = now()->valueOf() - $performance->started_at->valueOf();

        $performance->update(['status' => JurusPerformance::STATUS_SELESAI, 'duration_ms' => $durasiMs]);

        return $performance;
    }
}
