<?php

namespace App\Events\Gelanggang;

use App\Models\Arena;
use App\Models\SilatMatch;
use App\Support\Live\SaluranArena;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Gelanggang berpindah ke partai lain.
 *
 * Ikut ke channel publik, bukan cuma presence: overlay siaran menayangkan
 * partai gelanggang ini, dan papan siaran yang berganti partai tanpa aba-aba
 * -- lalu baru menyusul sedetik kemudian saat cache habis -- terlihat sebagai
 * kedipan yang tidak bisa dijelaskan penonton.
 */
class PartaiAktifBerubah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Arena $arena,
        public readonly ?SilatMatch $match,
        public readonly ?SilatMatch $sebelumnya,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return SaluranArena::untuk($this->arena->id);
    }

    public function broadcastAs(): string
    {
        return 'gelanggang.partai';
    }

    /**
     * Tanpa nama pengendali.
     *
     * Muatan ini menyentuh channel publik yang tidak diautentikasi. Siapa yang
     * memindahkan jadwal adalah urusan panel dan jejak audit, bukan urusan
     * penonton di rumah.
     */
    public function broadcastWith(): array
    {
        return [
            'arena_id' => $this->arena->id,
            'match_id' => $this->match?->id,
            'match_sebelumnya_id' => $this->sebelumnya?->id,
            'status' => $this->match?->status,
            'current_round' => $this->match?->current_round,
            'order_in_arena' => $this->match?->order_in_arena,
            'ditetapkan_at' => optional($this->arena->active_match_set_at)->toIso8601String(),
        ];
    }
}
