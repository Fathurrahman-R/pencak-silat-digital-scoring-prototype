<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\StatusTurnamen;
use App\Http\Controllers\Concerns\HandlesBulkDestroy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTournamentRequest;
use App\Http\Requests\Admin\UpdateTournamentRequest;
use App\Models\Tournament;
use App\Support\Table\TableBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentController extends Controller
{
    use HandlesBulkDestroy;

    public function index(): View
    {
        $table = TableBuilder::for(Tournament::query()->withCount(['arenas', 'weightClasses']))
            ->searchable(['name', 'organizer', 'venue'])
            ->sortable(['name', 'status', 'starts_on', 'created_at'], default: 'starts_on', direction: 'desc')
            ->filter('status', fn (Builder $query, string $value) => $query->where('status', $value));

        return view('admin.turnamen.index', [
            'tournaments' => $table->paginate(),
            'table' => $table,
            'statuses' => StatusTurnamen::options(),
        ]);
    }

    public function create(): View
    {
        return view('admin.turnamen.create');
    }

    /**
     * Kejuaraan baru langsung dibekali seluruh isi naskah 2025: setelan
     * peraturan, 174 kelas berat, dan 64 nomor jurus.
     *
     * Panitia lalu mematikan yang tidak dipertandingkan. Arah sebaliknya —
     * mulai dari kosong lalu mengetik satu per satu — adalah pekerjaan yang
     * salahnya baru ketahuan saat seorang atlet ditolak timbang badan di
     * hari-H.
     */
    public function store(StoreTournamentRequest $request, SusunMasterDataTurnamen $susun): RedirectResponse
    {
        $tournament = Tournament::create($this->payload($request->validated()));

        $susun($tournament);

        return redirect()
            ->route('admin.turnamen.edit', $tournament)
            ->with('success', "Kejuaraan “{$tournament->name}” dibuat beserta kelas dan nomor bawaan naskah 2025.");
    }

    public function edit(Tournament $tournament): View
    {
        return view('admin.turnamen.edit', [
            'tournament' => $tournament->loadCount(['arenas', 'weightClasses', 'jurusEvents']),
        ]);
    }

    /** Fragmen panel detail yang diambil drawer saat baris tabel diklik. */
    public function panel(Tournament $tournament): View
    {
        return view('admin.turnamen.panel', [
            'tournament' => $tournament->loadCount(['arenas', 'weightClasses', 'jurusEvents']),
        ]);
    }

    public function update(UpdateTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->update($this->payload($request->validated(), $tournament));

        return redirect()
            ->route('admin.turnamen.index')
            ->with('success', "Kejuaraan “{$tournament->name}” diperbarui.");
    }

    /**
     * Perpindahan status dipisahkan dari update biasa.
     *
     * Statusnya bukan sekadar label: begitu meninggalkan Draf, setelan
     * peraturan terkunci. Menyatukannya dengan formulir data akan membuat
     * penguncian itu terjadi sebagai efek samping dari menyunting alamat
     * tempat pertandingan.
     */
    public function updateStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $tujuan = StatusTurnamen::from($request->validate([
            'status' => ['required', 'string'],
        ])['status']);

        if (! $tournament->status->bisaPindahKe($tujuan)) {
            throw ValidationException::withMessages([
                'status' => "Kejuaraan berstatus {$tournament->status->label()} tidak bisa dipindahkan ke {$tujuan->label()}.",
            ]);
        }

        $tournament->update(['status' => $tujuan]);

        $catatan = $tujuan === StatusTurnamen::Berjalan
            ? ' Setelan peraturan kini terkunci.'
            : '';

        return back()->with('success', "Status kejuaraan menjadi {$tujuan->label()}.".$catatan);
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $tournament->delete();

        return redirect()->route('admin.turnamen.index')->with('success', 'Kejuaraan dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->destroyMany($request, Tournament::class, 'admin.turnamen.index');
    }

    public function export(): StreamedResponse
    {
        $table = TableBuilder::for(Tournament::query()->withCount('arenas'))
            ->searchable(['name', 'organizer', 'venue'])
            ->sortable(['name', 'starts_on'], default: 'starts_on', direction: 'desc');

        return $table->download(fn (Tournament $tournament): array => [
            'Nama' => $tournament->name,
            'Penyelenggara' => $tournament->organizer,
            'Tempat' => $tournament->venue,
            'Mulai' => $tournament->starts_on?->format('Y-m-d'),
            'Selesai' => $tournament->ends_on?->format('Y-m-d'),
            'Status' => $tournament->status->label(),
            'Gelanggang' => $tournament->arenas_count,
        ], 'kejuaraan-'.now()->format('Ymd-His').'.csv');
    }

    /** @param  array<string, mixed>  $data */
    private function payload(array $data, ?Tournament $tournament = null): array
    {
        /*
         * Slug hanya dibuat sekali, saat kejuaraan lahir.
         *
         * Sesudah itu ia tidak pernah ikut berubah walau namanya diperbaiki,
         * karena alamat halaman publiknya sudah beredar di poster, grup pesan
         * peserta, dan tautan siaran langsung yang dibagikan penonton.
         */
        if ($tournament === null) {
            $data['slug'] = $this->slugUnik($data['name']);
        }

        return $data;
    }

    private function slugUnik(string $nama): string
    {
        $dasar = Str::slug($nama) ?: 'kejuaraan';
        $slug = $dasar;
        $urutan = 1;

        while (Tournament::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $dasar.'-'.(++$urutan);
        }

        return $slug;
    }
}
