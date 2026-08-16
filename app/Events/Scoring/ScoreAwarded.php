<?php

namespace App\Events\Scoring;

use App\Enums\Sudut;
use App\Models\ScoreEvent;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu nilai yang sah karena mencapai ambang konsensus juri.
 *
 * score_events tidak pernah menyimpan identitas juri, jadi payloadnya aman
 * disiarkan apa adanya ke channel publik maupun privat.
 */
class ScoreAwarded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ScoreEvent $scoreEvent) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->scoreEvent->match->arena_id;

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
        return 'skor.terbit';
    }

    public function broadcastWith(): array
    {
        $kalkulator = app(TandingScoreCalculator::class);
        $match = $this->scoreEvent->match;

        return [
            'match_id' => $this->scoreEvent->match_id,
            'round' => $this->scoreEvent->round,
            'corner' => $this->scoreEvent->corner->value,
            'point_type' => $this->scoreEvent->point_type->value,
            'value' => $this->scoreEvent->value,
            'skor_merah' => $kalkulator->skor($match, Sudut::Merah),
            'skor_biru' => $kalkulator->skor($match, Sudut::Biru),
        ];
    }
}
