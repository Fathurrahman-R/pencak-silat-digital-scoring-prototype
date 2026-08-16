<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Events\Scoring\MatchStateChanged;
use App\Events\Scoring\PenaltyIssued;
use App\Events\Scoring\TimerTicked;
use App\Http\Controllers\Controller;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lapisan HTTP mesin scoring Tanding.
 *
 * Setiap aksi di sini hanya menerjemahkan permintaan jadi pemanggilan kelas
 * App\Support\Scoring yang sudah teruji, lalu menyiarkan hasilnya. Aturannya
 * sendiri -- tangga hukuman, jendela konsensus, siapa boleh apa dalam
 * partai -- tidak hidup di sini.
 */
class PartaiScoringController extends Controller
{
    public function __construct(
        private readonly MatchTimer $timer,
        private readonly TanggaHukuman $tangga,
        private readonly HitunganTeknik $hitungan,
        private readonly CatatInputJuri $catatInput,
        private readonly TandingScoreCalculator $kalkulator,
    ) {}

    /** Resync state penuh -- dipanggil tiap panel memuat ulang atau tersambung kembali. */
    public function state(Tournament $tournament, SilatMatch $match): JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        $match->load([
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            'bracket.weightClass', 'rounds', 'officials.user',
        ]);

        $rounds = $match->rounds->sortBy('round')->values()->map(fn ($r) => [
            'round' => $r->round,
            'status' => $r->status->value,
            'duration_ms' => $r->duration_ms,
            'sisa_ms' => $r->sisaMs(),
            'skor_merah' => $this->kalkulator->skorBabak($match, Sudut::Merah, $r->round),
            'skor_biru' => $this->kalkulator->skorBabak($match, Sudut::Biru, $r->round),
        ]);

        $penalti = fn (Sudut $sudut) => [
            'pembinaan' => $this->tangga->jumlahPembinaan($match, $sudut),
            'peringatan' => $this->tangga->jumlahPeringatan($match, $sudut),
            'diskualifikasi' => $this->tangga->sudahDiskualifikasi($match, $sudut),
        ];

        return response()->json([
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'current_round' => $match->current_round,
                'red' => $match->red ? [
                    'registration_id' => $match->red->id,
                    'athletes' => $match->red->athletes->pluck('name'),
                    'contingent' => $match->red->contingent->name,
                ] : null,
                'blue' => $match->blue ? [
                    'registration_id' => $match->blue->id,
                    'athletes' => $match->blue->athletes->pluck('name'),
                    'contingent' => $match->blue->contingent->name,
                ] : null,
                'winner_registration_id' => $match->winner_registration_id,
                'win_reason' => $match->win_reason,
                'ratified' => $match->disahkan(),
            ],
            'rounds' => $rounds,
            'skor_total' => [
                'merah' => $this->kalkulator->skor($match, Sudut::Merah),
                'biru' => $this->kalkulator->skor($match, Sudut::Biru),
            ],
            'hukuman' => [
                'merah' => $penalti(Sudut::Merah),
                'biru' => $penalti(Sudut::Biru),
            ],
            'tawaran_wmp' => $this->kalkulator->cekTawaranWmp($match)?->value,
            'officials' => $match->officials->map(fn ($o) => [
                'role' => $o->role, 'number' => $o->number, 'name' => $o->user->name,
            ]),
        ]);
    }

    public function mulaiBabak(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $data = $request->validate(['babak' => ['required', 'integer', 'min:1']]);

        $round = $this->jalankan(fn () => $this->timer->mulaiBabak($match, (int) $data['babak']));
        TimerTicked::dispatch($round);
        MatchStateChanged::dispatch($match->fresh());

        return back()->with('success', "Babak {$data['babak']} dimulai.");
    }

    public function jeda(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->jeda($this->babakAktifAtauGagal($match)));
        TimerTicked::dispatch($round);

        return back()->with('success', 'Babak dijeda.');
    }

    public function lanjutkan(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->lanjutkan($this->babakAktifAtauGagal($match)));
        TimerTicked::dispatch($round);

        return back()->with('success', 'Babak dilanjutkan.');
    }

    public function reset(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->reset($this->babakAktifAtauGagal($match)));
        TimerTicked::dispatch($round);

        return back()->with('success', 'Babak direset.');
    }

    public function selesaikanBabak(Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->selesaikanBabak($this->babakAktifAtauGagal($match)));
        TimerTicked::dispatch($round);

        return back()->with('success', "Babak {$round->round} diselesaikan.");
    }

    /** Mengakhiri partai dengan sebab tertentu -- KO, mutlak, WMP, undur diri, cedera, WO, atau menang angka. */
    public function akhiri(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'corner' => ['required', Rule::enum(Sudut::class)],
            'sebab' => ['required', 'string', 'in:angka,teknik,mutlak,wmp,undur_diri,cedera,wo'],
        ]);

        $sudut = Sudut::from($data['corner']);
        $pemenang = $sudut === Sudut::Merah ? $match->red : $match->blue;

        if ($pemenang === null) {
            throw ValidationException::withMessages(['corner' => 'Sudut ini belum punya peserta.']);
        }

        $selesai = $this->jalankan(fn () => $this->timer->akhiriPartai($match, $pemenang, $data['sebab']));
        MatchStateChanged::dispatch($selesai);

        return back()->with('success', 'Partai diakhiri.');
    }

    /** Dewan juri mengesahkan hasil -- syarat terakhir sebelum partai dianggap final. */
    public function sahkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        if (! $match->selesai() || $match->winner_registration_id === null) {
            throw ValidationException::withMessages(['match' => 'Partai belum punya pemenang untuk disahkan.']);
        }

        if ($match->disahkan()) {
            throw ValidationException::withMessages(['match' => 'Partai ini sudah disahkan.']);
        }

        $match->update(['ratified_at' => now(), 'ratified_by' => $request->user()->id]);

        MatchStateChanged::dispatch($match->fresh());

        return back()->with('success', 'Hasil partai disahkan.');
    }

    /** Juri mengirim satu nilai. */
    public function nilai(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1'],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'jenis' => ['required', Rule::enum(JenisSerangan::class)],
        ]);

        $input = ($this->catatInput)(
            $match,
            $request->user(),
            (int) $data['babak'],
            Sudut::from($data['corner']),
            JenisSerangan::from($data['jenis']),
        );

        if ($input->ditolak()) {
            return back()->with('warning', $input->rejected_reason);
        }

        return back()->with('success', 'Nilai terkirim.');
    }

    /** Wasit menjatuhkan sanksi -- pembinaan, teguran, atau peringatan sesuai tingkat pelanggarannya. */
    public function hukuman(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1'],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'tingkat' => ['required', Rule::enum(TingkatPelanggaran::class)],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $penalty = $this->jalankan(fn () => $this->tangga->catat(
            $match,
            Sudut::from($data['corner']),
            (int) $data['babak'],
            TingkatPelanggaran::from($data['tingkat']),
            $data['catatan'] ?? null,
            $request->user(),
        ));

        PenaltyIssued::dispatch($penalty);

        if ($penalty->diskualifikasi()) {
            MatchStateChanged::dispatch($match->fresh());
        }

        return back()->with('success', "{$penalty->tier->label()} dijatuhkan.");
    }

    /** Wasit mencatat hitungan terhadap pesilat yang jatuh. */
    public function hitungan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1'],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'hitungan' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $this->jalankan(fn () => $this->hitungan->catat(
            $match,
            Sudut::from($data['corner']),
            (int) $data['babak'],
            (int) $data['hitungan'],
            $request->user(),
        ));

        MatchStateChanged::dispatch($match->fresh());

        return back()->with('success', 'Hitungan tercatat.');
    }

    /** Dewan juri membatalkan satu nilai yang sudah terbit, tanpa menyunting riwayatnya. */
    public function batalkanNilai(Request $request, Tournament $tournament, SilatMatch $match, ScoreEvent $scoreEvent): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($scoreEvent->match_id === $match->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $scoreEvent->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['alasan'],
        ]);

        return back()->with('warning', 'Nilai dibatalkan.');
    }

    /** Dewan juri membatalkan satu sanksi yang sudah terbit, tanpa menyunting riwayatnya. */
    public function batalkanHukuman(Request $request, Tournament $tournament, SilatMatch $match, Penalty $penalty): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($penalty->match_id === $match->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $penalty->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['alasan'],
        ]);

        return back()->with('warning', 'Sanksi dibatalkan.');
    }

    private function babakAktifAtauGagal(SilatMatch $match)
    {
        return $match->babakAktif() ?? throw ValidationException::withMessages([
            'round' => 'Partai ini tidak punya babak yang sedang aktif.',
        ]);
    }

    /** Menerjemahkan RuntimeException dari lapisan domain jadi error validasi yang dibaca panel. */
    private function jalankan(\Closure $aksi): mixed
    {
        try {
            return $aksi();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['aksi' => $e->getMessage()]);
        }
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }
}
