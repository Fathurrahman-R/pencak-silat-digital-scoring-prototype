<?php

namespace App\Events\Jurus;

use App\Models\JurusPerformance;
use App\Support\Live\SaluranArena;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu penampilan Jurus berubah: nilai juri, timer, pengurangan, pengesahan,
 * atau diskualifikasi.
 *
 * SATU event untuk seluruh sebab, bukan lima event seperti Tanding, dan itu
 * disengaja. Tanding menyiarkan muatan yang dipakai langsung oleh layar
 * (angka skor dipasang dari muatan siarannya sendiri, supaya papan tidak diam
 * dua ratus milidetik setelah nilai terbit). Jurus tidak punya tekanan itu:
 * satu penampilan dinilai satu kali oleh tiap juri, bukan belasan kali per
 * babak. Yang dibutuhkan panelnya cuma aba-aba "ada yang berubah, tarik
 * ulang" -- dan aba-aba itu jauh lebih sulit salah daripada lima muatan
 * parsial yang masing-masing harus dijaga tetap sinkron dengan endpoint state.
 *
 * `sebab` ikut dikirim bukan untuk dipakai memutuskan apa yang digambar,
 * melainkan supaya panel bisa membedakan perubahan yang perlu diumumkan
 * (pengesahan) dari yang tidak, tanpa membandingkan dua salinan state.
 *
 * Tiga channel, semuanya tertutup:
 *
 *   - `jurus.penampilan.{id}` -- panel operator, panel juri, panel pengawas.
 *   - `jurus.battle.{id}`     -- halaman perbandingan battle, yang membaca
 *     DUA penampilan sekaligus dan menjadi tempat pemenang ditetapkan.
 *     Hanya disertakan kalau penampilannya memang berdiri di dalam battle;
 *     nomor berformat peringkat tidak punya battle sama sekali.
 *   - `arena.{id}` presence   -- panel yang mengikuti GELANGGANG, bukan
 *     penampilan: papan gelanggang, panel kendali, panel ketua. Mereka tidak
 *     tahu id penampilan yang sedang tayang sampai mereka menariknya, jadi
 *     tanpa channel ini nilai yang masuk tidak menggerakkan layar mana pun
 *     sampai seseorang memuat ulang. Hanya ada kalau penampilannya sudah
 *     dijadwalkan ke gelanggang.
 *
 * Tidak ada channel publik: papan skor penonton dan overlay vMix belum
 * menampilkan Jurus. Karena itu channel gelanggang diambil lewat
 * SaluranArena::presensi(), BUKAN ::untuk() -- yang terakhir ikut membuka
 * `public-live.{id}` begitu saklar overlay atau live score menyala. Saat nanti
 * Jurus memang ditayangkan ke penonton, tambahkan di sini dengan muatan yang
 * disaring, seperti JudgeInputReceived menyaring identitas juri.
 */
class PenampilanJurusBerubah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $performanceId;

    public readonly ?int $battleId;

    public readonly ?int $arenaId;

    public function __construct(JurusPerformance $performance, public readonly string $sebab)
    {
        $this->performanceId = $performance->id;
        $this->battleId = $performance->jurus_battle_id;
        $this->arenaId = $performance->arena_id;
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $saluran = [new PrivateChannel('jurus.penampilan.'.$this->performanceId)];

        if ($this->battleId !== null) {
            $saluran[] = new PrivateChannel('jurus.battle.'.$this->battleId);
        }

        return [...$saluran, ...SaluranArena::presensi($this->arenaId)];
    }

    public function broadcastAs(): string
    {
        return 'jurus.penampilan';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'performance_id' => $this->performanceId,
            'battle_id' => $this->battleId,
            /*
             * Pendengar channel gelanggang menerima siaran Tanding dan Jurus
             * di saluran yang sama. `arena_id` menyebut asalnya, supaya panel
             * Tanding bisa mengabaikan yang bukan urusannya tanpa menebak dari
             * bentuk muatannya.
             */
            'arena_id' => $this->arenaId,
            'sebab' => $this->sebab,
        ];
    }
}
