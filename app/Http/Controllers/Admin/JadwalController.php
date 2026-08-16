<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\PenjadwalPartai;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Penjadwalan partai ke gelanggang dan urutan tayangnya.
 *
 * Hanya menangani partai yang kedua sudutnya sudah terisi — partai yang masih
 * menunggu pemenang babak sebelumnya tidak muncul di sini sampai lawannya
 * pasti.
 */
class JadwalController extends Controller
{
    public function __construct(private readonly PenjadwalPartai $penjadwal) {}

    public function index(Tournament $tournament): View
    {
        $muatan = fn ($query) => $query->with([
            'red.athletes', 'red.contingent',
            'blue.athletes', 'blue.contingent',
            'bracket.weightClass',
        ]);

        $arenas = $tournament->arenas()->aktif()->get()
            ->map(function (Arena $arena) use ($muatan) {
                $arena->setRelation(
                    'matches',
                    $muatan($arena->matches()->orderBy('order_in_arena'))->get(),
                );

                return $arena;
            });

        $belumDijadwalkan = $muatan(
            SilatMatch::query()
                ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $tournament->id))
                ->belumDijadwalkan()
                ->where('status', '!=', SilatMatch::STATUS_SELESAI)
                ->whereNotNull('red_registration_id')
                ->whereNotNull('blue_registration_id'),
        )->get();

        return view('admin.jadwal.index', [
            'tournament' => $tournament,
            'arenas' => $arenas,
            'belumDijadwalkan' => $belumDijadwalkan,
        ]);
    }

    public function tetapkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'arena_id' => ['required', 'integer', Rule::exists('arenas', 'id')->where('tournament_id', $tournament->id)],
            'scheduled_at' => ['required', 'date'],
        ]);

        try {
            $this->penjadwal->tetapkan($match, Arena::findOrFail($data['arena_id']), Carbon::parse($data['scheduled_at']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Partai dijadwalkan.');
    }

    public function lepas(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $this->penjadwal->lepas($match);

        return back()->with('success', 'Jadwal dilepas — partai kembali ke antrean.');
    }

    public function urutkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate(['arah' => ['required', 'in:naik,turun']]);

        $this->penjadwal->urutkan($match, $data['arah'] === 'naik' ? -1 : 1);

        return back();
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }
}
