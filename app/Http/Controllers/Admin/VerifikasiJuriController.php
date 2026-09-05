<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JawabanVerifikasi;
use App\Enums\JenisVerifikasi;
use App\Enums\TingkatPelanggaran;
use App\Events\Scoring\MatchStateChanged;
use App\Events\Scoring\VerifikasiJuriBerubah;
use App\Http\Controllers\Controller;
use App\Models\JudgeVerification;
use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Scoring\PollingVerifikasi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Verifikasi juri -- Pasal 13.
 *
 * Berdiri sendiri di luar PartaiScoringController karena alurnya punya
 * pemiliknya sendiri: Wasit atau Ketua Pertandingan bertanya, tiga juri
 * menjawab, lalu yang bertanya menerapkan hasilnya. Menempelkannya ke
 * controller scoring yang sudah 685 baris hanya menyamarkan bahwa ini alur
 * terpisah dengan aturan aksesnya sendiri.
 *
 * Aturannya sendiri hidup di App\Support\Scoring\PollingVerifikasi.
 */
class VerifikasiJuriController extends Controller
{
    public function __construct(
        private readonly PollingVerifikasi $polling,
    ) {}

    /** Wasit atau Ketua Pertandingan mengajukan pertanyaan ke juri. */
    public function minta(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1'],
            'jenis' => ['required', Rule::enum(JenisVerifikasi::class)],
            'tingkat_pelanggaran' => ['nullable', Rule::enum(TingkatPelanggaran::class)],
            'score_event_id' => ['nullable', 'string', 'exists:score_events,id'],
            'penalty_id' => ['nullable', 'string', 'exists:penalties,id'],
        ]);

        /*
         * Kejadian yang ditunjuk wasit harus milik partai ini. Tanpa
         * pemeriksaan ini, id dari partai lain bisa disisipkan lewat
         * permintaan yang dirakit tangan, dan verifikasi akan menunjuk
         * kejadian yang tidak pernah terjadi di gelanggang ini.
         */
        $scoreEvent = $this->milikPartai(ScoreEvent::class, $data['score_event_id'] ?? null, $match);
        $penalty = $this->milikPartai(Penalty::class, $data['penalty_id'] ?? null, $match);

        $verifikasi = $this->jalankan(fn () => $this->polling->minta(
            $match,
            $request->user(),
            (int) $data['babak'],
            JenisVerifikasi::from($data['jenis']),
            isset($data['tingkat_pelanggaran']) ? TingkatPelanggaran::from($data['tingkat_pelanggaran']) : null,
            $scoreEvent,
            $penalty,
        ));

        $this->siarkan(fn () => VerifikasiJuriBerubah::dispatch($verifikasi));

        return $this->respond($request, 'success', 'Pertanyaan terkirim ke juri.');
    }

    /** Satu juri menjawab. */
    public function jawab(Request $request, Tournament $tournament, SilatMatch $match, JudgeVerification $verifikasi): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanVerifikasiPartai($match, $verifikasi);

        $data = $request->validate([
            'jawaban' => ['required', Rule::enum(JawabanVerifikasi::class)],
        ]);

        $verifikasi = $this->jalankan(fn () => $this->polling->jawab(
            $verifikasi,
            $request->user(),
            JawabanVerifikasi::from($data['jawaban']),
        ));

        $this->siarkan(fn () => VerifikasiJuriBerubah::dispatch($verifikasi));

        return $this->respond($request, 'success', 'Jawabanmu terkirim.');
    }

    /** Yang bertanya menerapkan hasilnya: nilai atau sanksi terbit, verifikasi ditutup. */
    public function terapkan(Request $request, Tournament $tournament, SilatMatch $match, JudgeVerification $verifikasi): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanVerifikasiPartai($match, $verifikasi);

        $verifikasi = $this->jalankan(fn () => $this->polling->terapkan($verifikasi, $request->user()));

        $this->siarkan(fn () => VerifikasiJuriBerubah::dispatch($verifikasi));

        /*
         * Skor dan tangga hukuman ikut berubah, dan itu tidak dibawa event
         * verifikasi. Panel operator, overlay siaran, dan halaman publik
         * mendengarkan perubahan partai, bukan perubahan verifikasi.
         */
        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, 'success', $this->polling->akibat($verifikasi));
    }

    /** Membatalkan tanpa menerapkan apa pun. Barisnya tetap tercatat. */
    public function batalkan(Request $request, Tournament $tournament, SilatMatch $match, JudgeVerification $verifikasi): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanVerifikasiPartai($match, $verifikasi);

        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        $verifikasi = $this->jalankan(fn () => $this->polling->batalkan(
            $verifikasi,
            $request->user(),
            $data['alasan'] ?? null,
        ));

        $this->siarkan(fn () => VerifikasiJuriBerubah::dispatch($verifikasi));

        return $this->respond($request, 'success', 'Verifikasi dibatalkan. Panel juri kembali menerima nilai.');
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function milikPartai(string $model, ?string $id, SilatMatch $match)
    {
        if ($id === null) {
            return null;
        }

        $baris = $model::query()->whereKey($id)->where('match_id', $match->id)->first();

        return $baris ?? throw ValidationException::withMessages([
            'kejadian' => 'Kejadian yang ditunjuk bukan milik partai ini.',
        ]);
    }

    private function pastikanVerifikasiPartai(SilatMatch $match, JudgeVerification $verifikasi): void
    {
        abort_unless($verifikasi->match_id === $match->id, 404);
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }

    private function respond(Request $request, string $tipe, string $pesan): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['tipe' => $tipe, 'pesan' => $pesan]);
        }

        return back()->with($tipe, $pesan);
    }

    /** Menerjemahkan RuntimeException lapisan domain jadi galat validasi yang dibaca panel. */
    private function jalankan(Closure $aksi): mixed
    {
        try {
            return $aksi();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['aksi' => $e->getMessage()]);
        }
    }

    /**
     * Siaran tidak boleh menggagalkan aksinya sendiri -- alasan lengkapnya di
     * PartaiScoringController::siarkan().
     */
    private function siarkan(Closure $penyiar): void
    {
        try {
            $penyiar();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
