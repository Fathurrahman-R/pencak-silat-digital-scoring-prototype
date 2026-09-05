<?php

namespace App\Events\Scoring;

use App\Models\SilatMatch;
use App\Support\Live\SaluranArena;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Babak lama dibuka atau ditutup untuk pencatatan susulan.
 *
 * Panel juri harus beralih SERENTAK. Juri yang panelnya terlambat beralih akan
 * menekan tombol nilai sambil mengira ia mencatat babak berjalan, sementara
 * nilainya masuk ke babak yang sudah lewat -- atau sebaliknya.
 */
class BabakSusulanBerubah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SilatMatch $match) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return SaluranArena::untuk($this->match->arena_id);
    }

    public function broadcastAs(): string
    {
        return 'babak-susulan.berubah';
    }

    /** Tanpa nama pengendali -- muatan ini menyentuh channel publik. */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'round' => $this->match->susulan_round,
            'terbuka' => $this->match->susulan_round !== null,
            'current_round' => $this->match->current_round,
        ];
    }
}
