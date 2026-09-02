<?php

namespace App\Events\Scoring;

use App\Models\JudgeInput;
use App\Models\MatchOfficial;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu penekanan tombol juri.
 *
 * Dua channel dengan MUATAN YANG BERBEDA, bukan satu muatan untuk dua
 * pendengar:
 *
 *   - Privat (`arena.{id}`) -- muatan penuh: siapa jurinya, alasan tolak,
 *     cap waktu server. Dipakai panel operator/wasit untuk indikator "juri
 *     menekan", dan panel juri untuk memastikan tekanannya sendiri sampai.
 *
 *   - Publik (`public-live.{id}`) -- hanya NOMOR jurinya (J1, J2, J3), sudut,
 *     dan tekniknya. Tanpa user id, tanpa nama, tanpa alasan tolak. Nomor juri
 *     adalah posisi tugas, bukan identitas orang: ia sudah tergambar di
 *     scorebug rancangan siaran, karena penonton yang melihat nilai tidak
 *     terbit berhak tahu bahwa yang sepakat memang belum cukup. FR-H-04
 *     menutup identitas dan input mentahnya, dan keduanya tetap tertutup.
 */
class JudgeInputReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly JudgeInput $input) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->input->match->arena_id;

        // Partai yang belum dijadwalkan ke gelanggang tidak punya channel
        // untuk disiarkan -- input tetap tersimpan di database, hanya
        // siarannya yang tidak ada tujuan.
        return $arenaId === null ? [] : [
            new PresenceChannel('arena.'.$arenaId),
            new Channel('public-live.'.$arenaId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'juri.input';
    }

    /**
     * SATU muatan untuk kedua channel, dan muatan itu sengaja yang paling
     * sempit: nomor juri, sudut, teknik, dan apakah tekanannya ditolak.
     *
     * Laravel mengirim muatan yang sama ke setiap channel sebuah event, jadi
     * "kirim lengkap ke privat, ringkas ke publik" tidak bisa dijanjikan di
     * sini -- yang bisa dijanjikan adalah tidak mengirim apa yang tidak
     * dibutuhkan siapa pun. Panel gelanggang pun hanya butuh NOMOR jurinya
     * untuk menyalakan indikator; id pengguna, nama, alasan tolak, dan cap
     * waktu server tidak pernah dipakai satu pendengar pun, dan sekarang
     * tidak lagi meninggalkan proses ini.
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->input->match_id,
            'round' => $this->input->round,
            'corner' => $this->input->corner->value,
            'point_type' => $this->input->point_type->value,
            'judge_number' => $this->nomorJuri(),
            'ditolak' => $this->input->ditolak(),
        ];
    }

    /** Nomor tugas juri di partai ini (1..n), bukan id penggunanya. */
    private function nomorJuri(): ?int
    {
        return $this->input->match->officials
            ->firstWhere(fn (MatchOfficial $o) => $o->role === MatchOfficial::ROLE_JURI
                && $o->user_id === $this->input->judge_user_id)
            ?->number;
    }
}
