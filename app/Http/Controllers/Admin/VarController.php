<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Sudut;
use App\Events\Scoring\MatchStateChanged;
use App\Http\Controllers\Controller;
use App\Models\ManagerProtest;
use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\VarReview;
use App\Support\Var\KeputusanProtesManajer;
use App\Support\Var\KeputusanVar;
use App\Support\Var\PengajuanProtes;
use App\Support\Var\PengajuanProtesManajer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Lapisan HTTP untuk keberatan -- VAR (Pasal 15) dan Protes Manajer (Pasal
 * 15 ayat 4). Sama seperti PartaiScoringController, aturannya sendiri hidup
 * di App\Support\Var yang sudah teruji; controller ini hanya menerjemahkan
 * permintaan dan menyiarkan hasilnya.
 */
class VarController extends Controller
{
    public function __construct(
        private readonly PengajuanProtes $ajukanVar,
        private readonly KeputusanVar $putuskanVar,
        private readonly PengajuanProtesManajer $ajukanManajer,
        private readonly KeputusanProtesManajer $putuskanManajer,
    ) {}

    public function ajukan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1'],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'kejadian' => ['required', 'string', 'max:255'],
            'score_event_id' => ['nullable', 'integer', Rule::exists('score_events', 'id')->where('match_id', $match->id)],
            'penalty_id' => ['nullable', 'integer', Rule::exists('penalties', 'id')->where('match_id', $match->id)],
        ]);

        $review = $this->jalankan(fn () => ($this->ajukanVar)(
            $match,
            Sudut::from($data['corner']),
            (int) $data['babak'],
            $data['kejadian'],
            $request->user(),
            isset($data['score_event_id']) ? ScoreEvent::find($data['score_event_id']) : null,
            isset($data['penalty_id']) ? Penalty::find($data['penalty_id']) : null,
        ));

        return $this->respond($request, $match, 'success', "Protes VAR diajukan, tenggat {$review->tenggat_at->format('H:i:s')}.");
    }

    public function putuskan(Request $request, Tournament $tournament, SilatMatch $match, VarReview $varReview): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($varReview->match_id === $match->id, 404);

        $data = $request->validate([
            'keputusan' => ['required', 'string', 'in:sah,tidak_sah'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->jalankan(fn () => ($this->putuskanVar)($varReview, $data['keputusan'], $data['catatan'] ?? null, $request->user()));

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', 'Keputusan VAR tercatat.');
    }

    public function ajukanManajer(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:255']]);

        $this->jalankan(fn () => $this->ajukanManajer->pertama($match, $data['catatan'] ?? null));

        return $this->respond($request, $match, 'success', 'Protes manajer tingkat pertama diajukan.');
    }

    public function banding(Request $request, Tournament $tournament, SilatMatch $match, ManagerProtest $managerProtest): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($managerProtest->match_id === $match->id, 404);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:255']]);

        $this->jalankan(fn () => $this->ajukanManajer->banding($managerProtest, $data['catatan'] ?? null));

        return $this->respond($request, $match, 'success', 'Banding diajukan ke Delegasi Teknik.');
    }

    public function putuskanManajer(Request $request, Tournament $tournament, SilatMatch $match, ManagerProtest $managerProtest): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($managerProtest->match_id === $match->id, 404);

        $data = $request->validate([
            'keputusan' => ['required', 'string', 'in:diterima,ditolak'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->jalankan(fn () => ($this->putuskanManajer)($managerProtest, $data['keputusan'], $data['catatan'] ?? null, $request->user()));

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', 'Keputusan protes manajer tercatat.');
    }

    private function respond(Request $request, SilatMatch $match, string $tipe, string $pesan): RedirectResponse|JsonResponse
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

    private function siarkan(Closure $penyiar): void
    {
        try {
            $penyiar();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }
}
