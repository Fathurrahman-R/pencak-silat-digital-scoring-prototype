<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\PenjadwalPartai;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
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

        /*
         * Kelengkapan aparat per partai.
         *
         * Partai yang jurinya belum lengkap sebelumnya tampil sama persis
         * dengan yang sudah siap. Panel juri tidak akan menerima nilai sampai
         * ketiganya ditugaskan, dan itu baru ketahuan saat partai dimulai di
         * depan penonton.
         */
        $jumlahJuri = $tournament->peraturan()->jumlah_juri_tanding;

        $terpasang = MatchOfficial::query()
            ->whereIn('match_id', $arenas->flatMap->matches->pluck('id'))
            ->get()
            ->groupBy('match_id');

        $aparat = $terpasang->map(fn ($baris) => [
            'wasit' => $baris->firstWhere('role', MatchOfficial::ROLE_WASIT)?->user_id !== null,
            'juri' => $baris->where('role', MatchOfficial::ROLE_JURI)->whereNotNull('user_id')->count(),
        ]);

        return view('admin.jadwal.index', [
            'tournament' => $tournament,
            'arenas' => $arenas,
            'belumDijadwalkan' => $belumDijadwalkan,
            'aparat' => $aparat,
            'jumlahJuri' => $jumlahJuri,
        ]);
    }

    /** Memindahkan partai ke urutan tertentu dalam satu tindakan. */
    public function pindahkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate(
            ['urutan' => ['required', 'integer', 'min:1']],
            attributes: ['urutan' => 'Nomor urut'],
        );

        try {
            $this->penjadwal->pindahkan($match, (int) $data['urutan']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Partai {$match->id} dipindahkan ke urutan {$data['urutan']}.");
    }

    /**
     * Jadwal siap cetak: satu tabel per gelanggang, urut tayang.
     *
     * Dibawa ke gelanggang sebagai kertas karena panitia meja gelanggang tidak
     * selalu punya layar, dan karena daftar yang dipegang di tangan tidak ikut
     * berubah saat seseorang menggeser urutan di panel. Tanpa kolom jam --
     * jadwal memang tidak menyimpannya.
     */
    public function cetak(Tournament $tournament): HttpResponse
    {
        $pdf = Pdf::loadView('admin.jadwal.cetak-pdf', [
            'tournament' => $tournament,
            'arenas' => $this->antreanPerGelanggang($tournament),
        ])->setPaper('a4');

        return $pdf->stream('jadwal-'.str($tournament->name)->slug().'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Isi tiap gelanggang, urut tayang.
     *
     * @return Collection<int, Arena>
     */
    private function antreanPerGelanggang(Tournament $tournament)
    {
        return $tournament->arenas()->aktif()->orderBy('sort_order')->get()
            ->each(fn (Arena $arena) => $arena->setRelation(
                'matches',
                $arena->matches()
                    ->whereNotNull('order_in_arena')
                    ->with(['red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent', 'bracket.weightClass'])
                    ->orderBy('order_in_arena')
                    ->get(),
            ));
    }

    public function tetapkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'arena_id' => ['required', 'integer', Rule::exists('arenas', 'id')->where('tournament_id', $tournament->id)],
        ]);

        try {
            $this->penjadwal->tetapkan($match, Arena::findOrFail($data['arena_id']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Partai dijadwalkan.');
    }

    public function lepas(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        try {
            $this->penjadwal->lepas($match);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

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
