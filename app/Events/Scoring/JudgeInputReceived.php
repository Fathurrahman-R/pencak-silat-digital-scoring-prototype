<?php

namespace App\Events\Scoring;

use App\Models\JudgeInput;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu penekanan tombol juri, disiarkan hanya ke channel privat gelanggang.
 *
 * Tidak pernah ke channel publik -- identitas juri dan input mentahnya
 * bukan konsumsi penonton (FR-H-04). Dipakai panel operator/wasit untuk
 * indikator titik "juri menekan" dan panel juri untuk konfirmasi input
 * miliknya sendiri terkirim.
 */
class JudgeInputReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly JudgeInput $input) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->input->match->arena_id;

        // Partai yang belum dijadwalkan ke gelanggang tidak punya channel
        // untuk disiarkan -- input tetap tersimpan di database, hanya
        // siarannya yang tidak ada tujuan.
        return $arenaId === null ? [] : [new PresenceChannel('arena.'.$arenaId)];
    }

    public function broadcastAs(): string
    {
        return 'juri.input';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->input->match_id,
            'round' => $this->input->round,
            'corner' => $this->input->corner->value,
            'point_type' => $this->input->point_type->value,
            'judge_id' => $this->input->judge_user_id,
            'judge_name' => $this->input->judge?->name,
            'ditolak' => $this->input->ditolak(),
            'rejected_reason' => $this->input->rejected_reason,
            'server_ts' => optional($this->input->server_ts)->toIso8601String(),
        ];
    }
}
