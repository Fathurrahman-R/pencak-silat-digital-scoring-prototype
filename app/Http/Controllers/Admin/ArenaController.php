<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArenaRequest;
use App\Http\Requests\Admin\UpdateArenaRequest;
use App\Models\Arena;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Gelanggang selalu hidup di dalam satu kejuaraan.
 *
 * Route-nya bersarang, dan tiap aksi memastikan gelanggang yang disebut
 * memang milik kejuaraan di alamatnya — kalau tidak, mengganti satu angka di
 * URL berarti menyunting gelanggang kejuaraan lain.
 */
class ArenaController extends Controller
{
    public function index(Tournament $tournament): View
    {
        return view('admin.gelanggang.index', [
            'tournament' => $tournament,
            'arenas' => $tournament->arenas()->get(),
        ]);
    }

    public function store(StoreArenaRequest $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validated();

        // Gelanggang baru masuk ke urutan paling belakang bila panitia tidak
        // menentukannya sendiri.
        $data['sort_order'] ??= (int) $tournament->arenas()->max('sort_order') + 1;

        $arena = $tournament->arenas()->create($data);

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Gelanggang “{$arena->name}” ditambahkan.");
    }

    public function update(UpdateArenaRequest $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $arena->update($request->validated());

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Gelanggang “{$arena->name}” diperbarui.");
    }

    public function destroy(Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $arena->delete();

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', 'Gelanggang dihapus.');
    }

    private function pastikanMilik(Tournament $tournament, Arena $arena): void
    {
        abort_unless($arena->tournament_id === $tournament->id, 404);
    }
}
