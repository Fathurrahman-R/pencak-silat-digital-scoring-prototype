<?php

namespace App\Support\Bagan;

use App\Models\Arena;
use App\Models\SilatMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menempatkan partai ke gelanggang dan menjaga urutan tayangnya.
 *
 * Jeda aman 30 menit bukan angka dari naskah 2025 — naskah tidak mengatur
 * jadwal sama sekali. Ini murni waktu berjalan kaki antar gelanggang plus
 * ganti pakaian tanding, dipilih sebagai konstanta implementasi seperti
 * ambang sepakat juri di Pasal 11.
 */
class PenjadwalPartai
{
    private const JEDA_AMAN_MENIT = 30;

    public function tetapkan(SilatMatch $match, Arena $arena, Carbon $waktu): SilatMatch
    {
        if (! $match->siapDipertandingkan()) {
            throw new RuntimeException('Partai ini belum punya dua peserta — belum bisa dijadwalkan.');
        }

        if ($match->selesai()) {
            throw new RuntimeException('Partai yang sudah selesai tidak bisa dijadwalkan ulang.');
        }

        $bentrok = $this->cariBentrok($match, $arena, $waktu);

        if ($bentrok !== null) {
            [$namaAtlet, $partaiLain] = $bentrok;

            throw new RuntimeException(
                "{$namaAtlet} sudah dijadwalkan di {$partaiLain->arena->name} pada ".
                $partaiLain->scheduled_at->translatedFormat('H:i').' — terlalu dekat dengan waktu ini.',
            );
        }

        $urutanTerakhir = SilatMatch::where('arena_id', $arena->id)->max('order_in_arena');

        $match->update([
            'arena_id' => $arena->id,
            'scheduled_at' => $waktu,
            'order_in_arena' => ((int) $urutanTerakhir) + 1,
        ]);

        return $match->refresh();
    }

    public function lepas(SilatMatch $match): SilatMatch
    {
        $match->update(['arena_id' => null, 'scheduled_at' => null, 'order_in_arena' => null]);

        return $match->refresh();
    }

    /** Menukar urutan tayang partai dengan tetangganya dalam gelanggang yang sama. */
    public function urutkan(SilatMatch $match, int $langkah): SilatMatch
    {
        if ($match->arena_id === null) {
            throw new RuntimeException('Partai ini belum dijadwalkan ke gelanggang mana pun.');
        }

        $tetangga = SilatMatch::where('arena_id', $match->arena_id)
            ->where('order_in_arena', $match->order_in_arena + $langkah)
            ->first();

        if ($tetangga === null) {
            return $match;
        }

        DB::transaction(function () use ($match, $tetangga) {
            [$urutanMatch, $urutanTetangga] = [$match->order_in_arena, $tetangga->order_in_arena];

            $match->update(['order_in_arena' => $urutanTetangga]);
            $tetangga->update(['order_in_arena' => $urutanMatch]);
        });

        return $match->refresh();
    }

    /**
     * Mencari partai lain dalam kejuaraan yang sama yang berbagi atlet dengan
     * $match, terjadwal di gelanggang berbeda dalam jeda aman.
     *
     * @return array{0: string, 1: SilatMatch}|null
     */
    private function cariBentrok(SilatMatch $match, Arena $arena, Carbon $waktu): ?array
    {
        $tournamentId = $match->bracket->weightClass->tournament_id;

        $atletMatch = collect([$match->red, $match->blue])
            ->filter()
            ->flatMap(fn ($r) => $r->athletes);

        $kandidat = SilatMatch::query()
            ->whereNotNull('arena_id')
            ->whereNotNull('scheduled_at')
            ->where('arena_id', '!=', $arena->id)
            ->where('id', '!=', $match->id)
            ->whereBetween('scheduled_at', [
                $waktu->clone()->subMinutes(self::JEDA_AMAN_MENIT),
                $waktu->clone()->addMinutes(self::JEDA_AMAN_MENIT),
            ])
            ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $tournamentId))
            ->with(['red.athletes', 'blue.athletes', 'arena'])
            ->get();

        foreach ($kandidat as $lain) {
            $atletLain = collect([$lain->red, $lain->blue])
                ->filter()
                ->flatMap(fn ($r) => $r->athletes);

            $sama = $atletMatch->first(fn ($a) => $atletLain->contains('id', $a->id));

            if ($sama !== null) {
                return [$sama->name, $lain];
            }
        }

        return null;
    }
}
