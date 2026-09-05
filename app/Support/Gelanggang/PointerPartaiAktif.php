<?php

namespace App\Support\Gelanggang;

use App\Events\Gelanggang\PartaiAktifBerubah;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\User;
use App\Support\Scoring\MatchTimer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya penulis `arenas.active_match_id`.
 *
 * Pola kepemilikannya sama dengan MatchTimer atas `current_round`: satu kelas
 * memegang satu invariant, sehingga pertanyaan "apa yang bisa mengubah ini"
 * punya satu jawaban yang bisa dibaca sekali.
 *
 * Pointer ini ORTOGONAL dengan `matches.status`, dan itu disengaja:
 *
 *   `matches.status`          daur hidup satu partai (MatchTimer yang menulis)
 *   `arenas.active_match_id`  apa yang sedang ditayangkan gelanggang (kelas ini)
 *
 * Karena itu mengakhiri partai TIDAK memajukan pointer. Partai yang sudah
 * selesai tetap ditunjuk sampai pengendali memindahkannya -- itulah yang
 * membuat papan hasil siaran bertahan di layar alih-alih berkedip hilang
 * beberapa detik setelah gong terakhir.
 */
class PointerPartaiAktif
{
    public function __construct(private readonly MatchTimer $timer) {}

    /**
     * Menunjuk partai yang ditayangkan gelanggang.
     *
     * @param  bool  $paksa  memindahkan pointer meski partai yang sedang
     *                       ditunjuk belum diakhiri -- untuk pengendali yang
     *                       terlanjur menekan "Mulai" pada partai yang keliru
     *
     * @throws RuntimeException
     */
    public function tunjuk(Arena $arena, SilatMatch $match, User $oleh, bool $paksa = false): Arena
    {
        if ($match->arena_id !== $arena->id) {
            throw new RuntimeException('Partai ini tidak dijadwalkan di gelanggang ini.');
        }

        $sebelumnya = $this->partaiAktif($arena);

        if ($sebelumnya !== null && $sebelumnya->id === $match->id) {
            return $arena;
        }

        if ($sebelumnya !== null) {
            $this->pastikanBolehDitinggalkan($sebelumnya, $paksa);
        }

        DB::transaction(function () use ($arena, $match, $oleh) {
            $arena->forceFill([
                'active_match_id' => $match->id,
                'active_match_set_at' => now(),
                'active_match_set_by' => $oleh->id,
            ])->save();

            $this->salinAparatGelanggang($arena, $match);
        });

        $this->umumkan($arena->refresh(), $match, $sebelumnya);

        return $arena;
    }

    /** Mengosongkan gelanggang -- tidak ada partai yang sedang ditayangkan. */
    public function kosongkan(Arena $arena, User $oleh): Arena
    {
        $sebelumnya = $this->partaiAktif($arena);

        if ($sebelumnya === null) {
            return $arena;
        }

        $this->pastikanBolehDitinggalkan($sebelumnya, paksa: false);

        $arena->forceFill([
            'active_match_id' => null,
            'active_match_set_at' => now(),
            'active_match_set_by' => $oleh->id,
        ])->save();

        $this->umumkan($arena->refresh(), null, $sebelumnya);

        return $arena;
    }

    /**
     * Partai yang ditunjuk gelanggang ini, kalau masih sah.
     *
     * Saringan `arena_id` bukan kueri berlebihan: partai bisa dilepas dari
     * jadwal setelah pointer menunjuknya, dan pointer basi yang menunjuk
     * partai tanpa gelanggang akan menayangkan partai milik gelanggang lain.
     */
    public function partaiAktif(Arena $arena): ?SilatMatch
    {
        if ($arena->active_match_id === null) {
            return null;
        }

        return SilatMatch::whereKey($arena->active_match_id)
            ->where('arena_id', $arena->id)
            ->first();
    }

    /** Partai berikutnya di antrean gelanggang, menurut urutan tayangnya. */
    public function berikutnya(Arena $arena): ?SilatMatch
    {
        return $this->antrean($arena)
            ->first(fn (SilatMatch $partai) => $partai->id !== $arena->active_match_id
                && $partai->status !== SilatMatch::STATUS_SELESAI);
    }

    /**
     * Antrean partai gelanggang ini, urut tayang.
     *
     * Partai yang belum punya `order_in_arena` ditaruh di belakang, bukan
     * dibuang: ia tetap dijadwalkan di sini dan pengendali harus bisa
     * memilihnya.
     *
     * @return Collection<int, SilatMatch>
     */
    public function antrean(Arena $arena, int $limit = 20): Collection
    {
        return SilatMatch::where('arena_id', $arena->id)
            ->with(['red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent', 'bracket.weightClass'])
            ->orderByRaw('order_in_arena is null')
            ->orderBy('order_in_arena')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @throws RuntimeException
     */
    private function pastikanBolehDitinggalkan(SilatMatch $partai, bool $paksa): void
    {
        if ($partai->status !== SilatMatch::STATUS_BERLANGSUNG) {
            return;
        }

        if (! $paksa) {
            throw new RuntimeException(
                'Partai yang sedang berjalan belum diakhiri. Akhiri dulu, atau pindah paksa.',
            );
        }

        /*
         * Statusnya sengaja TIDAK diubah jadi selesai.
         *
         * Pengendali yang salah memilih partai lalu terlanjur menekan "Mulai"
         * harus bisa kembali ke partai itu dan melanjutkannya. Menandainya
         * selesai berarti memaksa pemenang ditetapkan untuk partai yang belum
         * dimainkan -- kekeliruan yang jauh lebih mahal daripada yang sedang
         * diperbaiki.
         */
        $babak = $partai->babakAktif();

        if ($babak?->berjalan()) {
            $this->timer->jeda($babak);
        }
    }

    /**
     * Menyalin aparat gelanggang ke partai yang baru ditunjuk.
     *
     * Baris `match_officials` yang SUDAH ada tidak ditimpa. Sekretariat yang
     * sengaja menugaskan aparat khusus untuk partai final tidak boleh
     * kehilangan penugasannya hanya karena pengendali menekan tombol pindah.
     *
     * Kenapa disalin, bukan dibaca langsung dari gelanggang saat dibutuhkan:
     * penugasan gelanggang bisa berubah di tengah hari, sementara pertanyaan
     * "siapa yang bertugas di partai nomor 14" harus punya jawaban yang tetap
     * setahun kemudian. Berita acara mencetaknya.
     */
    private function salinAparatGelanggang(Arena $arena, SilatMatch $match): void
    {
        $sudahAda = MatchOfficial::where('match_id', $match->id)->exists();

        if ($sudahAda) {
            return;
        }

        $baris = ArenaOfficial::where('arena_id', $arena->id)
            ->get()
            ->map(fn (ArenaOfficial $aparat) => [
                /*
                 * Kunci dibangkitkan di sini, bukan diserahkan ke basis data.
                 * Penyisipan massal lewat query builder melewati model, jadi
                 * HasUlids tidak pernah dijalankan -- dan kolomnya bukan lagi
                 * auto-increment yang bisa mengisi dirinya sendiri.
                 */
                'id' => (string) Str::ulid(),
                'match_id' => $match->id,
                'user_id' => $aparat->user_id,
                'role' => $aparat->role,
                'number' => $aparat->number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($baris === []) {
            return;
        }

        MatchOfficial::insert($baris);
    }

    private function umumkan(Arena $arena, ?SilatMatch $match, ?SilatMatch $sebelumnya): void
    {
        /*
         * Cache state dilupakan lebih dulu, bukan dibiarkan kedaluwarsa
         * sendiri. Umurnya cuma satu detik, tapi satu detik itu jatuh persis
         * di antara pengendali menekan dan layar besar berganti -- dan yang
         * ditayangkannya selama itu adalah partai yang sudah bukan miliknya.
         */
        Cache::forget("overlay-state-arena-{$arena->id}");
        Cache::forget("live-state-arena-{$arena->id}");

        PartaiAktifBerubah::dispatch($arena, $match, $sebelumnya);
    }
}
