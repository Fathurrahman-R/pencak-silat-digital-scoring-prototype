<?php

namespace App\Events\Scoring;

use App\Models\SilatMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Status partai berubah -- mulai berlangsung, selesai, atau disahkan dewan juri. */
class MatchStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SilatMatch $match) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->match->arena_id;

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
        return 'partai.berubah';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'status' => $this->match->status,
            'current_round' => $this->match->current_round,
            'winner_registration_id' => $this->match->winner_registration_id,
            'win_reason' => $this->match->win_reason,
            'ratified' => $this->match->disahkan(),
        ];
    }
}
