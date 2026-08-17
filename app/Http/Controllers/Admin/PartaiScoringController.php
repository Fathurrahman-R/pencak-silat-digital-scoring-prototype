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
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Lapisan HTTP mesin scoring Tanding.
 *
 * Setiap aksi di sini hanya menerjemahkan permintaan jadi pemanggilan kelas
 * App\Support\Scoring yang sudah teruji, lalu menyiarkan hasilnya. Aturannya
 * sendiri -- tangga hukuman, jendela konsensus, siapa boleh apa dalam
 * partai -- tidak hidup di sini.
 *
 * Setiap aksi mengembalikan JSON kalau diminta (dipakai panel gelanggang
 * yang mengandalkan Echo, bukan reload halaman) atau redirect biasa kalau
 * tidak (fallback formulir tanpa JavaScript). Pembaruan tampilan yang
 * sesungguhnya datang lewat siaran Reverb, bukan dari respons HTTP ini --
 * jadi payload JSON-nya sengaja tipis, hanya pesan untuk umpan balik segera.
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

        return response()->json($this->stateArray($match));
    }

    public function operator(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('silat.operator', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
        ]);
    }

    public function wasit(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('silat.wasit', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
        ]);
    }

    public function dewanJuri(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('silat.dewan-juri', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
        ]);
    }

    public function keberatan(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('silat.keberatan', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
        ]);
    }

    public function juri(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('silat.juri', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
            'manifestUrl' => route('admin.turnamen.partai.juri.manifest', [$tournament, $match]),
        ]);
    }

    /** Berita acara partai -- FR-J-03: skor per babak, daftar nilai, daftar hukuman, kolom tanda tangan. */
    public function beritaAcara(Tournament $tournament, SilatMatch $match): HttpResponse
    {
        $this->pastikanMilik($tournament, $match);

        $match->load([
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            'bracket.weightClass.tournament', 'rounds', 'officials.user',
        ]);

        $babakSekarang = $match->current_round ?? $match->rounds->max('round') ?? 1;

        $rounds = $match->rounds->sortBy('round')->values()->map(fn ($r) => [
            'round' => $r->round,
            'skor_merah' => $this->kalkulator->skorBabak($match, Sudut::Merah, $r->round),
            'skor_biru' => $this->kalkulator->skorBabak($match, Sudut::Biru, $r->round),
        ]);

        $pdf = Pdf::loadView('admin.rekap.berita-acara', [
            'match' => $match,
            'rounds' => $rounds,
            'skorTotal' => ['merah' => $this->kalkulator->skor($match, Sudut::Merah), 'biru' => $this->kalkulator->skor($match, Sudut::Biru)],
            'nilai' => $match->scoreEvents()->berlaku()->orderBy('server_ts')->get(),
            'hukuman' => $match->penalties()->berlaku()->orderBy('created_at')->get(),
            'peraturan' => $babakSekarang,
        ])->setPaper('a4');

        $namaBerkas = 'berita-acara-'.$match->id.'.pdf';

        return $pdf->stream($namaBerkas);
    }

    /**
     * Manifest PWA dibuat per partai, bukan berkas statis -- `start_url`
     * menunjuk balik ke partai yang sedang dibuka juri, supaya "Tambahkan ke
     * layar utama" yang dilakukan di gelanggang tertentu memang membuka
     * gelanggang itu lagi, bukan halaman generik.
     */
    public function manifest(Tournament $tournament, SilatMatch $match): JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        return response()->json([
            'name' => 'Panel Juri — '.$match->bracket->weightClass->name,
            'short_name' => 'Juri',
            'description' => 'Papan tombol juri untuk penilaian pertandingan Tanding.',
            'start_url' => route('admin.turnamen.partai.juri', [$tournament, $match]),
            'scope' => route('admin.turnamen.partai.juri', [$tournament, $match]),
            'display' => 'fullscreen',
            'orientation' => 'portrait',
            'background_color' => '#0b0b0c',
            'theme_color' => '#0b0b0c',
            'icons' => [
                ['src' => '/icons/juri.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /** Alamat resync + seluruh aksi, dikirim ke panel Alpine lewat @js(...) -- JS tidak pernah menyusun route Laravel sendiri. */
    private function konfigPanel(Tournament $tournament, SilatMatch $match): array
    {
        return [
            'matchId' => $match->id,
            'arenaId' => $match->arena_id,
            'state' => route('admin.turnamen.partai.state', [$tournament, $match]),
            'timerMulai' => route('admin.turnamen.partai.timer.mulai', [$tournament, $match]),
            'timerJeda' => route('admin.turnamen.partai.timer.jeda', [$tournament, $match]),
            'timerLanjut' => route('admin.turnamen.partai.timer.lanjut', [$tournament, $match]),
            'timerReset' => route('admin.turnamen.partai.timer.reset', [$tournament, $match]),
            'timerSelesai' => route('admin.turnamen.partai.timer.selesai-babak', [$tournament, $match]),
            'akhiri' => route('admin.turnamen.partai.akhiri', [$tournament, $match]),
            'sahkan' => route('admin.turnamen.partai.sahkan', [$tournament, $match]),
            'nilai' => route('admin.turnamen.partai.nilai', [$tournament, $match]),
            'hukuman' => route('admin.turnamen.partai.hukuman', [$tournament, $match]),
            'hitungan' => route('admin.turnamen.partai.hitungan', [$tournament, $match]),
            'nilaiBatal' => route('admin.turnamen.partai.nilai.batal', [$tournament, $match, '__ID__']),
            'hukumanBatal' => route('admin.turnamen.partai.hukuman.batal', [$tournament, $match, '__ID__']),
            'varAjukan' => route('admin.turnamen.partai.keberatan.var.ajukan', [$tournament, $match]),
            'varPutuskan' => route('admin.turnamen.partai.keberatan.var.putuskan', [$tournament, $match, '__ID__']),
            'protesManajerAjukan' => route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$tournament, $match]),
            'protesManajerBanding' => route('admin.turnamen.partai.keberatan.protes-manajer.banding', [$tournament, $match, '__ID__']),
            'protesManajerPutuskan' => route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$tournament, $match, '__ID__']),
        ];
    }

    public function mulaiBabak(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $data = $request->validate(['babak' => ['required', 'integer', 'min:1']]);

        $round = $this->jalankan(fn () => $this->timer->mulaiBabak($match, (int) $data['babak']));
        $this->siarkan(fn () => TimerTicked::dispatch($round));
        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', "Babak {$data['babak']} dimulai.");
    }

    public function jeda(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->jeda($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak dijeda.');
    }

    public function lanjutkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->lanjutkan($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak dilanjutkan.');
    }

    public function reset(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->reset($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak direset.');
    }

    public function selesaikanBabak(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $round = $this->jalankan(fn () => $this->timer->selesaikanBabak($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', "Babak {$round->round} diselesaikan.");
    }

    /** Mengakhiri partai dengan sebab tertentu -- KO, mutlak, WMP, undur diri, cedera, WO, atau menang angka. */
    public function akhiri(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
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
        $this->siarkan(fn () => MatchStateChanged::dispatch($selesai));

        return $this->respond($request, $match, 'success', 'Partai diakhiri.');
    }

    /** Dewan juri mengesahkan hasil -- syarat terakhir sebelum partai dianggap final. */
    public function sahkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        if (! $match->selesai() || $match->winner_registration_id === null) {
            throw ValidationException::withMessages(['match' => 'Partai belum punya pemenang untuk disahkan.']);
        }

        if ($match->disahkan()) {
            throw ValidationException::withMessages(['match' => 'Partai ini sudah disahkan.']);
        }

        $match->update(['ratified_at' => now(), 'ratified_by' => $request->user()->id]);

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', 'Hasil partai disahkan.');
    }

    /** Juri mengirim satu nilai. */
    public function nilai(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
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
            return $this->respond($request, $match, 'warning', $input->rejected_reason);
        }

        return $this->respond($request, $match, 'success', 'Nilai terkirim.');
    }

    /** Wasit menjatuhkan sanksi -- pembinaan, teguran, atau peringatan sesuai tingkat pelanggarannya. */
    public function hukuman(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
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

        $this->siarkan(fn () => PenaltyIssued::dispatch($penalty));

        if ($penalty->diskualifikasi()) {
            $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));
        }

        return $this->respond($request, $match, 'success', "{$penalty->tier->label()} dijatuhkan.");
    }

    /** Wasit mencatat hitungan terhadap pesilat yang jatuh. */
    public function hitungan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
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

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', 'Hitungan tercatat.');
    }

    /** Dewan juri membatalkan satu nilai yang sudah terbit, tanpa menyunting riwayatnya. */
    public function batalkanNilai(Request $request, Tournament $tournament, SilatMatch $match, ScoreEvent $scoreEvent): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($scoreEvent->match_id === $match->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $scoreEvent->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['alasan'],
        ]);

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'warning', 'Nilai dibatalkan.');
    }

    /** Dewan juri membatalkan satu sanksi yang sudah terbit, tanpa menyunting riwayatnya. */
    public function batalkanHukuman(Request $request, Tournament $tournament, SilatMatch $match, Penalty $penalty): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        abort_unless($penalty->match_id === $match->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $penalty->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['alasan'],
        ]);

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'warning', 'Sanksi dibatalkan.');
    }

    /** @return array<string, mixed> */
    private function stateArray(SilatMatch $match): array
    {
        $match->load([
            'red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent',
            'bracket.weightClass.tournament', 'rounds', 'officials.user',
        ]);

        $peraturan = $match->bracket->weightClass->tournament->peraturan();
        $babakSekarang = $match->current_round ?? 1;

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
            'teguran' => $this->tangga->jumlahTeguran($match, $sudut, $babakSekarang),
            'peringatan' => $this->tangga->jumlahPeringatan($match, $sudut),
            'diskualifikasi' => $this->tangga->sudahDiskualifikasi($match, $sudut),
        ];

        return [
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
            'peraturan' => [
                'jumlah_juri' => $peraturan->jumlah_juri_tanding,
                'ambang_sepakat' => $peraturan->ambang_sepakat,
                'window_konsensus_ms' => $peraturan->window_konsensus_ms,
                'jumlah_babak' => $peraturan->babakUntuk($match->bracket->weightClass->golongan_usia)['jumlah'],
            ],
            'officials' => $match->officials->map(fn ($o) => [
                'role' => $o->role, 'number' => $o->number, 'name' => $o->user->name, 'user_id' => $o->user_id,
            ]),
            'riwayat' => $this->riwayat($match),
            'keberatan' => $this->keberatanArray($match),
        ];
    }

    /** @return array<string, mixed> */
    private function keberatanArray(SilatMatch $match): array
    {
        $kartu = $match->protestCards()->get()->keyBy(fn ($k) => $k->corner->value);
        $sisaKartu = fn (string $corner) => $kartu->has($corner)
            ? $kartu[$corner]->sisaKartu()
            : config('scoring.var.kartu_protes.tanding');

        $varReviews = $match->varReviews()->with(['pemutus'])->latest('id')->limit(20)->get()->map(fn ($v) => [
            'id' => $v->id,
            'round' => $v->round,
            'corner' => $v->corner->value,
            'kejadian' => $v->kejadian,
            'diajukan_at' => $v->diajukan_at->toIso8601String(),
            'tenggat_at' => $v->tenggat_at->toIso8601String(),
            'sisa_detik' => $v->sisaDetik(),
            'lewat_tenggat' => $v->lewatTenggat(),
            'keputusan' => $v->keputusan,
            'catatan' => $v->catatan,
        ]);

        $protesManajer = $match->managerProtests()->latest('id')->get()->map(fn ($p) => [
            'id' => $p->id,
            'level' => $p->level,
            'parent_id' => $p->parent_id,
            'diajukan_at' => $p->diajukan_at->toIso8601String(),
            'tenggat_keputusan_at' => $p->tenggat_keputusan_at->toIso8601String(),
            'keputusan' => $p->keputusan,
            'catatan' => $p->catatan,
            'final' => $p->final(),
        ]);

        return [
            'kartu' => ['merah' => $sisaKartu('red'), 'biru' => $sisaKartu('blue')],
            'var_reviews' => $varReviews,
            'protes_manajer' => $protesManajer,
        ];
    }

    /**
     * Nilai dan hukuman terbaru yang masih berlaku, dipakai panel dewan juri
     * untuk membatalkan salah satunya. Digabung satu daftar terurut waktu
     * supaya panel tidak perlu menyandingkan dua daftar terpisah sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayat(SilatMatch $match): array
    {
        $nilai = $match->scoreEvents()->berlaku()->latest('id')->limit(30)->get()->map(fn ($s) => [
            'tipe' => 'nilai',
            'id' => $s->id,
            'round' => $s->round,
            'corner' => $s->corner->value,
            'label' => "{$s->point_type->label()} ({$s->value})",
            'waktu' => $s->server_ts->toIso8601String(),
        ]);

        $hukuman = $match->penalties()->berlaku()->latest('id')->limit(30)->get()->map(fn ($p) => [
            'tipe' => 'hukuman',
            'id' => $p->id,
            'round' => $p->round,
            'corner' => $p->corner->value,
            'label' => "{$p->tier->label()} ".($p->points !== null ? $p->points : '(DQ)'),
            'waktu' => $p->created_at->toIso8601String(),
        ]);

        return $nilai->concat($hukuman)->sortByDesc('waktu')->values()->all();
    }

    /** JSON tipis untuk panel real-time, redirect+flash untuk fallback formulir biasa. */
    private function respond(Request $request, SilatMatch $match, string $tipe, string $pesan): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['tipe' => $tipe, 'pesan' => $pesan]);
        }

        return back()->with($tipe, $pesan);
    }

    private function babakAktifAtauGagal(SilatMatch $match)
    {
        return $match->babakAktif() ?? throw ValidationException::withMessages([
            'round' => 'Partai ini tidak punya babak yang sedang aktif.',
        ]);
    }

    /** Menerjemahkan RuntimeException dari lapisan domain jadi error validasi yang dibaca panel. */
    private function jalankan(Closure $aksi): mixed
    {
        try {
            return $aksi();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['aksi' => $e->getMessage()]);
        }
    }

    /**
     * Menyiarkan lewat Reverb tanpa membiarkan kegagalannya menggagalkan
     * aksi itu sendiri.
     *
     * Timer, skor, dan hukuman sudah tersimpan ke database sebelum baris ini
     * dipanggil -- itulah sumber kebenarannya. Siaran cuma jalan cepat untuk
     * mendorong pembaruan ke panel lain; kalau server Reverb sedang mati atau
     * tidak terjangkau, gelanggang tetap harus bisa lanjut mencatat skor.
     * Panel yang tersambung akan mengejar ketinggalan lewat resync begitu
     * memuat ulang atau tersambung kembali.
     */
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
