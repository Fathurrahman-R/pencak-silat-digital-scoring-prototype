<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArenaRequest;
use App\Http\Requests\Admin\UpdateArenaRequest;
use App\Models\Arena;
use App\Models\Tournament;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'arenas' => $tournament->arenas()->with('operators:id,name')->get(),
            // Daftar calon operator dipakai modal penugasan. Operator
            // nonaktif tidak ditawarkan -- akunnya tidak bisa masuk.
            'calonOperator' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', 'operator-it'))
                ->orderBy('name')
                ->get(['id', 'name']),
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

    /**
     * Menetapkan siapa saja operator gelanggang ini.
     *
     * Operator ditugaskan per gelanggang, bukan per partai: ia duduk di satu
     * gelanggang sepanjang hari, dan penugasannya ikut berlaku untuk partai
     * yang baru dijadwalkan ke sini kemudian. Tanpa penugasan ini, panel
     * gelanggang menolak seluruh aksinya.
     */
    public function simpanOperator(Request $request, Tournament $tournament, Arena $arena): RedirectResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $data = $request->validate([
            'operator_id' => ['nullable', 'array'],
            'operator_id.*' => [
                'required',
                Rule::exists('users', 'id')->where('is_active', true),
                // Peran diperiksa di sini, bukan cuma keberadaan penggunanya:
                // menugaskan bendahara jadi operator gelanggang akan lolos
                // kalau yang dicek hanya `exists`.
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if (! User::find($nilai)?->hasRole('operator-it')) {
                        $gagal('Pengguna yang dipilih bukan Operator IT.');
                    }
                },
            ],
        ]);

        $arena->operators()->sync($data['operator_id'] ?? []);

        return redirect()
            ->route('admin.turnamen.gelanggang.index', $tournament)
            ->with('success', "Operator “{$arena->name}” diperbarui.");
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
