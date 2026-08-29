<?php

namespace App\Events\Scoring;

use App\Models\JudgeVerification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Verifikasi juri dibuka, dijawab satu juri, mendapat hasil, atau ditutup.
 *
 * Satu event untuk keempat peristiwa itu, bukan empat event terpisah, karena
 * yang harus dilakukan panel sama saja pada keempatnya: menggambar ulang
 * keadaan verifikasi. Panel juri terutama harus beralih SERENTAK -- juri yang
 * panelnya terlambat beralih akan menekan tombol nilai untuk kejadian yang
 * sedang dipertanyakan.
 *
 * # Yang sengaja TIDAK disiarkan
 *
 * Isi jawaban tiap juri. Channel gelanggang didengarkan semua panel di
 * gelanggang itu, panel juri termasuk, dan juri yang melihat rekannya sudah
 * menjawab "sudut merah" tidak lagi menjawab apa yang dilihatnya sendiri --
 * persis alasan yang sama kenapa indikator titik juri tidak muncul di panel
 * juri sebelum jendela konsensus tutup.
 *
 * Yang disiarkan hanya SIAPA yang sudah menjawab, tanpa jawabannya. Itu cukup
 * untuk panel wasit menampilkan "menunggu Juri 3", dan tidak cukup untuk
 * menggiring juri mana pun.
 *
 * Wasit dan Ketua Pertandingan melihat jawaban lengkap dengan menarik state
 * panelnya, yang memang disaring menurut peran -- bukan dari siaran ini.
 */
class VerifikasiJuriBerubah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly JudgeVerification $verifikasi) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $arenaId = $this->verifikasi->match->arena_id;

        return $arenaId === null ? [] : [new PresenceChannel('arena.'.$arenaId)];
    }

    public function broadcastAs(): string
    {
        return 'verifikasi.berubah';
    }

    public function broadcastWith(): array
    {
        $verifikasi = $this->verifikasi->loadMissing('answers');

        return [
            'match_id' => $verifikasi->match_id,
            'verifikasi_id' => $verifikasi->id,
            'round' => $verifikasi->round,
            'jenis' => $verifikasi->jenis->value,
            'pertanyaan' => $verifikasi->jenis->pertanyaan(),
            'tingkat_pelanggaran' => $verifikasi->tingkat_pelanggaran?->value,
            'status' => $verifikasi->status,

            /*
             * Hasil ikut disiarkan, dan itu aman: ia baru ada setelah ambang
             * tercapai, dan pada saat itu jawaban juri yang belum masuk sudah
             * tidak bisa mengubah apa pun.
             */
            'hasil' => $verifikasi->hasil?->value,
            'sudah_diterapkan' => $verifikasi->sudahDiterapkan(),

            // Siapa, bukan apa.
            'sudah_menjawab' => $verifikasi->answers
                ->map(fn ($j) => ['judge_user_id' => $j->judge_user_id, 'judge_number' => $j->judge_number])
                ->values(),
        ];
    }
}
