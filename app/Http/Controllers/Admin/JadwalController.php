<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\JurusBattle;
use App\Models\JurusPerformance;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\PenjadwalJurus;
use App\Support\Bagan\PenjadwalPartai;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(
        private readonly PenjadwalPartai $penjadwal,
        private readonly PenjadwalJurus $penjadwalJurus,
    ) {}

    public function index(Tournament $tournament): View
    {
        $muatan = fn ($query) => $query->with([
            'red.athletes', 'red.contingent',
            'blue.athletes', 'blue.contingent',
            'bracket.weightClass',
        ]);

        $arenas = $tournament->arenas()->aktif()->with('aparat')->get()
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

        /*
         * Kelengkapan dibaca dari GELANGGANG untuk partai yang belum pernah
         * ditayangkan.
         *
         * Sejak penugasan aparat pindah ke gelanggang, `match_officials` baru
         * terisi saat pengendali menunjuk partainya. Membaca tabel itu saja
         * membuat SELURUH jadwal pagi hari tertulis "Wasit belum ada" padahal
         * tiap gelanggang sudah punya aparat lengkap -- peringatan yang salah
         * setiap hari akan berhenti dibaca justru sebelum hari ia benar.
         */
        $aparatGelanggang = $arenas->mapWithKeys(fn ($arena) => [
            $arena->id => [
                'wasit' => $arena->aparat->firstWhere('role', MatchOfficial::ROLE_WASIT) !== null,
                'juri' => $arena->aparat->where('role', MatchOfficial::ROLE_JURI)->count(),
            ],
        ]);

        $aparat = $arenas->flatMap->matches->mapWithKeys(function ($partai) use ($terpasang, $aparatGelanggang) {
            $baris = $terpasang->get($partai->id);

            if ($baris === null) {
                return [$partai->id => $aparatGelanggang[$partai->arena_id] ?? ['wasit' => false, 'juri' => 0]];
            }

            return [$partai->id => [
                'wasit' => $baris->firstWhere('role', MatchOfficial::ROLE_WASIT)?->user_id !== null,
                'juri' => $baris->where('role', MatchOfficial::ROLE_JURI)->whereNotNull('user_id')->count(),
            ]];
        });

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

    /*
     * ---------------------------------------------------------------------
     * Tab Jurus
     *
     * Tab terpisah, bukan satu daftar bercampur. Nomor urut gelanggang untuk
     * Tanding dan Jurus berdiri sendiri-sendiri di kolom yang berbeda tabel,
     * dan menampilkannya sebagai satu antrean berarti menjanjikan urutan
     * gabungan yang tidak pernah ada -- panitia akan membacanya sebagai "yang
     * ini sesudah yang itu", padahal keduanya bernomor satu.
     * ---------------------------------------------------------------------
     */

    public function indexJurus(Tournament $tournament): View
    {
        $arenas = $tournament->arenas()->aktif()->orderBy('sort_order')->get()
            ->each(fn (Arena $arena) => $arena->setRelation(
                'jurusPerformances',
                $this->muatanPenampilan(
                    JurusPerformance::query()->diGelanggang($arena),
                )->get(),
            ));

        return view('admin.jadwal.jurus', [
            'tournament' => $tournament,
            'arenas' => $arenas,
            'battleSiap' => $this->battleSiap($tournament),
            'penampilanSiap' => $this->penampilanSiap($tournament),
        ]);
    }

    /** Jadwal Jurus siap cetak, satu tabel per gelanggang, urut tayang. */
    public function cetakJurus(Tournament $tournament): HttpResponse
    {
        $arenas = $tournament->arenas()->aktif()->orderBy('sort_order')->get()
            ->each(fn (Arena $arena) => $arena->setRelation(
                'jurusPerformances',
                $this->muatanPenampilan(
                    JurusPerformance::query()->diGelanggang($arena)->whereNotNull('order_in_arena'),
                )->get(),
            ));

        $pdf = Pdf::loadView('admin.jadwal.jurus-cetak-pdf', [
            'tournament' => $tournament,
            'arenas' => $arenas,
        ])->setPaper('a4');

        return $pdf->stream('jadwal-jurus-'.str($tournament->name)->slug().'-'.now()->format('Ymd-His').'.pdf');
    }

    public function tetapkanBattle(Request $request, Tournament $tournament, JurusBattle $jurusBattle): RedirectResponse
    {
        $this->pastikanMilikJurus($tournament, $jurusBattle);

        $data = $request->validate([
            'arena_id' => ['required', 'integer', Rule::exists('arenas', 'id')->where('tournament_id', $tournament->id)],
        ]);

        try {
            $this->penjadwalJurus->tetapkanBattle($jurusBattle, Arena::findOrFail($data['arena_id']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Battle dijadwalkan — kedua sudutnya masuk antrean gelanggang, biru lebih dulu.');
    }

    public function lepasBattle(Tournament $tournament, JurusBattle $jurusBattle): RedirectResponse
    {
        $this->pastikanMilikJurus($tournament, $jurusBattle);

        try {
            $this->penjadwalJurus->lepasBattle($jurusBattle);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jadwal dilepas — battle kembali ke antrean.');
    }

    public function tetapkanPenampilan(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse
    {
        $this->pastikanMilikPenampilan($tournament, $performance);

        $data = $request->validate([
            'arena_id' => ['required', 'integer', Rule::exists('arenas', 'id')->where('tournament_id', $tournament->id)],
        ]);

        try {
            $this->penjadwalJurus->tetapkan($performance, Arena::findOrFail($data['arena_id']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penampilan dijadwalkan.');
    }

    public function lepasPenampilan(Tournament $tournament, JurusPerformance $performance): RedirectResponse
    {
        $this->pastikanMilikPenampilan($tournament, $performance);

        /*
         * Sudut yang berdiri di dalam battle dilepas berpasangan, lewat
         * battle-nya. Melepas satu sudut saja meninggalkan lawannya sendirian
         * di antrean gelanggang, dan yang membacanya di panel kendali tidak
         * punya cara menebak ke mana pasangannya pergi.
         */
        if ($performance->jurus_battle_id !== null) {
            return back()->with(
                'error',
                'Penampilan ini satu sudut dari sebuah battle — lepaskan battle-nya, bukan satu sudutnya saja.',
            );
        }

        try {
            $this->penjadwalJurus->lepas($performance);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jadwal dilepas — penampilan kembali ke antrean.');
    }

    public function pindahkanPenampilan(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse
    {
        $this->pastikanMilikPenampilan($tournament, $performance);

        $data = $request->validate(
            ['urutan' => ['required', 'integer', 'min:1']],
            attributes: ['urutan' => 'Nomor urut'],
        );

        try {
            $this->penjadwalJurus->pindahkan($performance, (int) $data['urutan']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penampilan dipindahkan ke urutan {$data['urutan']}.");
    }

    /**
     * Battle yang kedua sudutnya sudah pasti dan belum dijadwalkan.
     *
     * Yang lawannya belum ditentukan sengaja tidak muncul, sama seperti partai
     * Tanding: menjadwalkan tempat yang penghuninya belum pasti berarti
     * mencetak jadwal yang harus dicabut lagi.
     *
     * @return Collection<int, JurusBattle>
     */
    private function battleSiap(Tournament $tournament)
    {
        return JurusBattle::query()
            ->whereHas('bracket.jurusEvent', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->whereNull('arena_id')
            ->where('status', '!=', JurusBattle::STATUS_SELESAI)
            ->whereNotNull('red_registration_id')
            ->whereNotNull('blue_registration_id')
            ->with([
                'bracket.jurusEvent',
                'red.athletes', 'red.contingent',
                'blue.athletes', 'blue.contingent',
            ])
            ->orderBy('jurus_bracket_id')
            ->orderBy('round')
            ->orderBy('position')
            ->get();
    }

    /**
     * Penampilan lepas yang belum dijadwalkan -- nomor berformat peringkat.
     *
     * Yang berdiri di dalam battle tidak ikut: ia dijadwalkan lewat battle-nya
     * supaya kedua sudut tidak pernah terpisah gelanggang.
     *
     * @return Collection<int, JurusPerformance>
     */
    private function penampilanSiap(Tournament $tournament)
    {
        return $this->muatanPenampilan(
            JurusPerformance::query()
                ->whereHas('jurusEvent', fn ($q) => $q->where('tournament_id', $tournament->id))
                ->whereNull('arena_id')
                ->whereNull('jurus_battle_id')
                ->whereNull('ratified_at'),
        )->orderBy('jurus_event_id')->orderBy('id')->get();
    }

    /** @param  Builder<JurusPerformance>  $query */
    private function muatanPenampilan($query)
    {
        return $query->with([
            'jurusEvent',
            'registration.athletes',
            'registration.contingent',
        ]);
    }

    private function pastikanMilikJurus(Tournament $tournament, JurusBattle $battle): void
    {
        abort_unless($battle->bracket->jurusEvent->tournament_id === $tournament->id, 404);
    }

    private function pastikanMilikPenampilan(Tournament $tournament, JurusPerformance $performance): void
    {
        abort_unless($performance->jurusEvent->tournament_id === $tournament->id, 404);
    }
}
