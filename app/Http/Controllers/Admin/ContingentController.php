<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesContingents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContingentRequest;
use App\Models\Contingent;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Table\TableBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ContingentController extends Controller
{
    use ScopesContingents;

    public function index(Tournament $tournament): View
    {
        $table = TableBuilder::for(
            $this->scopeKontingen($tournament->contingents()->getQuery())
                ->withCount(['athletes', 'registrations'])
                ->with('official'),
        )
            ->searchable(['name', 'region', 'contact_name'])
            ->sortable(['name', 'region', 'created_at'], default: 'name');

        return view('admin.kontingen.index', [
            'tournament' => $tournament,
            'contingents' => $table->paginate(),
            'table' => $table,
        ]);
    }

    public function create(Tournament $tournament): View
    {
        return view('admin.kontingen.create', [
            'tournament' => $tournament,
            'officials' => $this->pilihanOfficial(),
        ]);
    }

    public function store(StoreContingentRequest $request, Tournament $tournament): RedirectResponse
    {
        $contingent = $tournament->contingents()->create($request->validated());

        return redirect()
            ->route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])
            ->with('success', "Kontingen “{$contingent->name}” terdaftar.");
    }

    public function edit(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        return view('admin.kontingen.edit', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'officials' => $this->pilihanOfficial(),
        ]);
    }

    public function update(StoreContingentRequest $request, Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        $contingent->update($request->validated());

        return redirect()
            ->route('admin.turnamen.kontingen.index', $tournament)
            ->with('success', "Kontingen “{$contingent->name}” diperbarui.");
    }

    public function panel(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        return view('admin.kontingen.panel', [
            'tournament' => $tournament,
            'contingent' => $contingent->loadCount(['athletes', 'registrations'])->load('official'),
        ]);
    }

    public function destroy(Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        $contingent->delete();

        return redirect()
            ->route('admin.turnamen.kontingen.index', $tournament)
            ->with('success', 'Kontingen dihapus.');
    }

    /**
     * Pengguna yang bisa ditunjuk sebagai official.
     *
     * @return array<int|string, string>
     */
    private function pilihanOfficial(): array
    {
        return ['' => 'Belum ditentukan'] + User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
