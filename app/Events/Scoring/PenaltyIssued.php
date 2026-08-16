<?php

namespace App\Events\Scoring;

use App\Models\Penalty;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Satu sanksi -- Pasal 11.6.d.4. Payload sengaja tanpa `note`: itu keterangan wasit untuk berita acara, bukan tontonan. */
class PenaltyIssued implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Penalty $penalty) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->penalty->match->arena_id;

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
        return 'hukuman.terbit';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->penalty->match_id,
            'round' => $this->penalty->round,
            'corner' => $this->penalty->corner->value,
            'tier' => $this->penalty->tier->value,
            'level' => $this->penalty->level,
            'points' => $this->penalty->points,
            'diskualifikasi' => $this->penalty->diskualifikasi(),
        ];
    }
}
