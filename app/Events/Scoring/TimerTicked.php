<?php

namespace App\Events\Scoring;

use App\Models\MatchRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Timer babak berubah state (mulai/jeda/lanjut/selesai).
 *
 * Bukan siaran tiap 250ms -- itu butuh proses latar yang hidup terus-
 * menerus, sesuatu yang di luar cakupan siklus request/response biasa.
 * Sebagai gantinya, klien menghitung mundur sendiri secara lokal dari
 * `started_at`/`accumulated_ms`/`duration_ms` yang otoritatif ini begitu
 * menerima event, dan menyinkronkan ulang tiap kali menerima event
 * berikutnya atau tersambung kembali. Hasilnya sama halus tanpa jitter
 * tambahan, tanpa perlu proses tambahan di server.
 */
class TimerTicked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly MatchRound $round) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->round->match->arena_id;

        if ($arenaId === null) {
            return [];
        }

        return [
            new PresenceChannel('arena.'.$arenaId),
            new Channel('public-live.'.$arenaId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'timer.berubah';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->round->match_id,
            'round' => $this->round->round,
            'status' => $this->round->status->value,
            'duration_ms' => $this->round->duration_ms,
            'accumulated_ms' => $this->round->accumulated_ms,
            'started_at' => optional($this->round->started_at)->toIso8601String(),
        ];
    }
}
