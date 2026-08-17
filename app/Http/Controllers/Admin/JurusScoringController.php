<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JurusDeduction;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Tournament;
use App\Support\Jurus\JurusScoreCalculator;
use App\Support\Jurus\JurusTimer;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lapisan HTTP mesin scoring Jurus. Sejajar dengan PartaiScoringController,
 * tapi jauh lebih ramping -- satu penampilan berjalan sekali dari awal
 * sampai selesai tanpa babak, sudut, atau konsensus real-time, jadi tidak
 * ada window/threshold untuk dijaga di sini.
 */
class JurusScoringController extends Controller
{
    public function __construct(
        private readonly JurusTimer $timer,
        private readonly JurusScoreCalculator $kalkulator,
    ) {}

    /** Daftar semua nomor Jurus turnamen -- pintu masuk ke kendali penampilan tiap nomor. */
    public function daftarNomor(Tournament $tournament): View
    {
        $jurusEvents = $tournament->jurusEvents()->aktif()
            ->withCount(['performances', 'registrations as registrations_sah_count' => fn ($q) => $q->sah()])
            ->orderBy('golongan_usia')->orderBy('sort_order')
            ->get();

        return view('admin.jurus.daftar', ['tournament' => $tournament, 'jurusEvents' => $jurusEvents]);
    }

    public function index(Tournament $tournament, JurusEvent $jurusEvent): View
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $performances = $jurusEvent->performances()
            ->with(['registration.athletes', 'registration.contingent', 'scores'])
            ->get();

        return view('admin.jurus.index', [
            'tournament' => $tournament,
            'jurusEvent' => $jurusEvent,
            'performances' => $performances,
            'peringkat' => $this->kalkulator->peringkat($performances),
        ]);
    }

    /** Membuat penampilan untuk tiap pendaftaran terverifikasi yang belum punya penampilan tahap ini. */
    public function generate(Request $request, Tournament $tournament, JurusEvent $jurusEvent): RedirectResponse
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $data = $request->validate(['tahap' => ['required', 'string', 'in:penyisihan,semifinal,final']]);

        $sudahAda = $jurusEvent->performances()->where('tahap', $data['tahap'])->pluck('registration_id');

        $jurusEvent->registrations()->sah()
            ->whereNotIn('id', $sudahAda)
            ->get()
            ->each(fn ($registrasi) => JurusPerformance::create([
                'jurus_event_id' => $jurusEvent->id,
                'registration_id' => $registrasi->id,
                'tahap' => $data['tahap'],
            ]));

        return back()->with('success', 'Penampilan dibuat untuk pendaftaran yang belum punya penampilan tahap ini.');
    }

    public function operator(Tournament $tournament, JurusPerformance $performance): View
    {
        $this->pastikanMilikPerforma($tournament, $performance);

        return view('jurus.operator', [
            'tournament' => $tournament,
            'performance' => $performance->load('jurusEvent', 'registration.athletes', 'registration.contingent'),
            'config' => $this->konfigPanel($tournament, $performance),
        ]);
    }

    public function juri(Request $request, Tournament $tournament, JurusPerformance $performance): View
    {
        $this->pastikanMilikPerforma($tournament, $performance);

        return view('jurus.juri', [
            'tournament' => $tournament,
            'performance' => $performance->load('jurusEvent', 'registration.athletes', 'registration.contingent'),
            'config' => [...$this->konfigPanel($tournament, $performance), 'judgeUserId' => $request->user()->id],
        ]);
    }

    public function state(Tournament $tournament, JurusPerformance $performance): JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $performance->load(['scores.juri', 'deductions.pencatat', 'registration.athletes', 'registration.contingent']);

        return response()->json([
            'performance' => [
                'id' => $performance->id,
                'status' => $performance->status,
                'started_at' => optional($performance->started_at)->toIso8601String(),
                'duration_ms' => $performance->duration_ms,
                'didiskualifikasi' => $performance->didiskualifikasi,
                'ratified' => $performance->disahkan(),
            ],
            'peserta' => [
                'nama' => $performance->registration->athletes->pluck('name')->implode(', '),
                'kontingen' => $performance->registration->contingent->name,
            ],
            'skor' => [
                'median' => $this->kalkulator->median($performance),
                'total_pengurangan' => $this->kalkulator->totalPengurangan($performance),
                'akhir' => $this->kalkulator->skorAkhir($performance),
            ],
            'nilai_juri' => $performance->scores->map(fn ($s) => [
                'judge_user_id' => $s->judge_user_id, 'nama' => $s->juri->name, 'value' => (float) $s->value,
            ]),
            'pengurangan' => $performance->deductions->where('voided_at', null)->values()->map(fn ($d) => [
                'id' => $d->id, 'tier' => $d->tier, 'alasan' => $d->alasan, 'jumlah' => (float) $d->jumlah, 'pencatat' => $d->pencatat?->name,
            ]),
        ]);
    }

    public function mulaiTimer(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $this->jalankan(fn () => $this->timer->mulai($performance));

        return $this->respond($request, 'success', 'Penampilan dimulai.');
    }

    public function berhentiTimer(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $this->jalankan(fn () => $this->timer->berhenti($performance));

        return $this->respond($request, 'success', 'Penampilan diselesaikan.');
    }

    /** Juri mengirim (atau memperbarui) nilai akhirnya sendiri untuk penampilan ini. */
    public function nilai(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);

        $skala = config('scoring.jurus.skala');
        $data = $request->validate([
            'value' => ['required', 'numeric', "min:{$skala['min']}", "max:{$skala['max']}"],
        ]);

        JurusScore::updateOrCreate(
            ['performance_id' => $performance->id, 'judge_user_id' => $request->user()->id],
            ['value' => $data['value']],
        );

        return $this->respond($request, 'success', 'Nilai tersimpan.');
    }

    /** Juri mencatat pengurangan 0.01 -- kesalahan rincian gerak, urutan, gerakan tertinggal, senjata terlepas. */
    public function penguranganJuri(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        JurusDeduction::create([
            'performance_id' => $performance->id, 'tier' => JurusDeduction::TIER_JURI,
            'alasan' => $data['alasan'], 'jumlah' => config('scoring.jurus.pengurangan.juri'),
            'created_by' => $request->user()->id,
        ]);

        return $this->respond($request, 'success', 'Pengurangan 0.01 dicatat.');
    }

    /** Pengawas/Dewan Wasit Juri mencatat pengurangan 0.50 -- pelanggaran waktu, keluar gelanggang, dan sejenisnya. */
    public function penguranganPengawas(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        JurusDeduction::create([
            'performance_id' => $performance->id, 'tier' => JurusDeduction::TIER_PENGAWAS,
            'alasan' => $data['alasan'], 'jumlah' => config('scoring.jurus.pengurangan.pengawas'),
            'created_by' => $request->user()->id,
        ]);

        return $this->respond($request, 'success', 'Pengurangan 0.50 dicatat.');
    }

    public function batalkanPengurangan(Request $request, Tournament $tournament, JurusPerformance $performance, JurusDeduction $deduction): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        abort_unless($deduction->performance_id === $performance->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $deduction->update(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $data['alasan']]);

        return $this->respond($request, 'warning', 'Pengurangan dibatalkan.');
    }

    /** Pengawas/Dewan Wasit Juri menetapkan diskualifikasi -- skor akhirnya jadi 0,00 (Pasal 12.1.e.4.h). */
    public function diskualifikasi(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $performance->update(['didiskualifikasi' => true]);

        return $this->respond($request, 'warning', 'Penampilan didiskualifikasi.');
    }

    public function sahkan(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);

        if (! $performance->selesai()) {
            throw ValidationException::withMessages(['performance' => 'Penampilan belum selesai.']);
        }

        if ($performance->disahkan()) {
            throw ValidationException::withMessages(['performance' => 'Penampilan ini sudah disahkan.']);
        }

        /*
         * Pasal 16.1.b: juri Jurus minimal 4 orang dan harus genap --
         * median dari jumlah ganjil tetap bisa dihitung secara matematis,
         * tapi bukan itu yang diatur naskah, jadi pengesahan ditahan
         * sampai jumlahnya benar. Diskualifikasi dikecualikan karena
         * skornya sudah pasti 0,00 terlepas dari berapa juri yang sempat menilai.
         */
        if (! $performance->didiskualifikasi) {
            $minimal = $tournament->peraturan()->jumlah_juri_jurus;
            $terkumpul = $performance->scores()->count();

            if ($terkumpul < $minimal || $terkumpul % 2 !== 0) {
                throw ValidationException::withMessages([
                    'performance' => "Nilai juri belum lengkap: {$terkumpul} juri sudah menilai, dibutuhkan minimal {$minimal} juri dan jumlahnya harus genap.",
                ]);
            }
        }

        $performance->update(['ratified_at' => now(), 'ratified_by' => $request->user()->id]);

        return $this->respond($request, 'success', 'Skor penampilan disahkan.');
    }

    /** @return array<string, mixed> */
    private function konfigPanel(Tournament $tournament, JurusPerformance $performance): array
    {
        return [
            'performanceId' => $performance->id,
            'state' => route('admin.turnamen.jurus.penampilan.state', [$tournament, $performance]),
            'mulai' => route('admin.turnamen.jurus.penampilan.timer.mulai', [$tournament, $performance]),
            'berhenti' => route('admin.turnamen.jurus.penampilan.timer.berhenti', [$tournament, $performance]),
            'nilai' => route('admin.turnamen.jurus.penampilan.nilai', [$tournament, $performance]),
            'penguranganJuri' => route('admin.turnamen.jurus.penampilan.pengurangan-juri', [$tournament, $performance]),
            'penguranganPengawas' => route('admin.turnamen.jurus.penampilan.pengurangan-pengawas', [$tournament, $performance]),
            'penguranganBatal' => route('admin.turnamen.jurus.penampilan.pengurangan.batal', [$tournament, $performance, '__ID__']),
            'diskualifikasi' => route('admin.turnamen.jurus.penampilan.diskualifikasi', [$tournament, $performance]),
            'sahkan' => route('admin.turnamen.jurus.penampilan.sahkan', [$tournament, $performance]),
        ];
    }

    private function respond(Request $request, string $tipe, string $pesan): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['tipe' => $tipe, 'pesan' => $pesan]);
        }

        return back()->with($tipe, $pesan);
    }

    private function jalankan(Closure $aksi): mixed
    {
        try {
            return $aksi();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['aksi' => $e->getMessage()]);
        }
    }

    private function pastikanMilik(Tournament $tournament, JurusEvent $jurusEvent): void
    {
        abort_unless($jurusEvent->tournament_id === $tournament->id, 404);
    }

    private function pastikanMilikPerforma(Tournament $tournament, JurusPerformance $performance): void
    {
        abort_unless($performance->jurusEvent->tournament_id === $tournament->id, 404);
    }
}
