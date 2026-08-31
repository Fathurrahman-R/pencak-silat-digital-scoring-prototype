<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Events\Scoring\MatchStateChanged;
use App\Events\Scoring\PenaltyIssued;
use App\Events\Scoring\TimerTicked;
use App\Http\Controllers\Controller;
use App\Models\JudgeVerification;
use App\Models\MatchOfficial;
use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\PollingVerifikasi;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private readonly PollingVerifikasi $polling,
    ) {}

    /** Resync state penuh -- dipanggil tiap panel memuat ulang atau tersambung kembali. */
    public function state(Request $request, Tournament $tournament, SilatMatch $match): JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        return response()->json($this->stateArray($match, $request->user()));
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

        $nilai = $match->scoreEvents()->berlaku()->with('judgeInputs:id,score_event_id,judge_user_id')
            ->orderBy('server_ts')->get();
        $hukuman = $match->penalties()->berlaku()->orderBy('created_at')->get();

        /*
         * Berita acara ditandatangani dan diarsipkan. Tanpa kolom penekan, ia
         * mencatat bahwa sebuah nilai terbit tapi tidak mencatat siapa yang
         * menerbitkannya -- justru pertanyaan pertama yang muncul saat hasilnya
         * dipersoalkan di kemudian hari. Datanya sudah tersimpan di
         * `judge_inputs`; yang kurang hanya penyajiannya.
         */
        $sebutan = $match->officials->mapWithKeys(fn ($o) => [$o->user_id => $o->sebutan()]);

        $penekan = $nilai->mapWithKeys(fn ($n) => [$n->id => $n->judgeInputs
            ->map(fn ($i) => $sebutan[$i->judge_user_id] ?? null)
            ->filter()->unique()->sort()->values()->implode(', ') ?: null]);

        $pencatat = $hukuman->mapWithKeys(fn ($h) => [$h->id => $sebutan[$h->created_by] ?? null]);

        /*
         * Verifikasi juri -- Pasal 13.
         *
         * Wajib masuk berita acara, dan bukan sekadar sebagai catatan kaki.
         * Nilai yang lahir dari verifikasi tidak punya judge_inputs (tidak ada
         * juri yang menekan tombolnya), jadi tanpa bagian ini kolom "Juri" di
         * Daftar Nilai kosong dan dokumen yang ditandatangani mencatat sebuah
         * jatuhan +3 yang seolah muncul tanpa penerbit -- justru baris yang
         * paling dipersoalkan saat hasilnya digugat.
         *
         * Jawaban tiap juri ditampilkan satu per satu, bukan cuma hasil
         * akhirnya, karena Pasal 15 membolehkan pelatih memprotes keputusan
         * verifikasi: yang diprotes adalah jawabannya, dan protes tanpa akses
         * ke jawaban itu tidak bisa disusun.
         *
         * Yang dibatalkan ikut tercatat. Verifikasi yang dibatalkan adalah
         * bagian dari yang terjadi di gelanggang.
         */
        $verifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->whereIn('status', [JudgeVerification::SELESAI, JudgeVerification::DIBATALKAN])
            ->with(['answers' => fn ($q) => $q->orderBy('judge_number'), 'peminta:id,name'])
            ->orderBy('diminta_at')
            ->get();

        $pdf = Pdf::loadView('admin.rekap.berita-acara', [
            'match' => $match,
            'rounds' => $rounds,
            'skorTotal' => ['merah' => $this->kalkulator->skor($match, Sudut::Merah), 'biru' => $this->kalkulator->skor($match, Sudut::Biru)],
            'nilai' => $nilai,
            'hukuman' => $hukuman,
            'penekan' => $penekan,
            'pencatat' => $pencatat,
            'verifikasi' => $verifikasi,
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
            /*
             * PNG didaftarkan lebih dulu, vektor menyusul. Sebagian peluncur --
             * iOS Safari yang paling menonjol -- menolak memasang ikon vektor
             * dan akan melewati manifest ini seluruhnya kalau tidak ada raster
             * yang bisa dipakai. Ukurannya 192 dan 512 karena itu dua ukuran
             * yang diperiksa Chrome saat menentukan sebuah halaman layak
             * dipasang atau tidak.
             */
            'icons' => [
                ['src' => '/icons/juri-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/icons/juri-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/icons/juri.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /** Alamat resync + seluruh aksi, dikirim ke panel Alpine lewat @js(...) -- JS tidak pernah menyusun route Laravel sendiri. */
    private function konfigPanel(Tournament $tournament, SilatMatch $match): array
    {
        return [
            'matchId' => $match->id,
            'arenaId' => $match->arena_id,
            /*
             * Panel perlu tahu ia sedang dipegang siapa, bukan cuma partai
             * apa. Layar verifikasi juri memakainya untuk membedakan "kamu
             * belum menjawab" dari "kamu sudah, tinggal menunggu yang lain" --
             * dua keadaan yang tampilannya harus jauh berbeda supaya juri
             * tidak menekan dua kali.
             */
            'userId' => auth()->id(),
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
            'verifikasiMinta' => route('admin.turnamen.partai.verifikasi.minta', [$tournament, $match]),
            'verifikasiJawab' => route('admin.turnamen.partai.verifikasi.jawab', [$tournament, $match, '__ID__']),
            'verifikasiTerapkan' => route('admin.turnamen.partai.verifikasi.terapkan', [$tournament, $match, '__ID__']),
            'verifikasiBatalkan' => route('admin.turnamen.partai.verifikasi.batalkan', [$tournament, $match, '__ID__']),
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
        $this->pastikanAparatPartai($match, $request->user());
        $data = $request->validate(['babak' => ['required', 'integer', 'min:1']]);

        $round = $this->jalankan(fn () => $this->timer->mulaiBabak($match, (int) $data['babak']));
        $this->siarkan(fn () => TimerTicked::dispatch($round));
        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', "Babak {$data['babak']} dimulai.");
    }

    public function jeda(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $round = $this->jalankan(fn () => $this->timer->jeda($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak dijeda.');
    }

    public function lanjutkan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $round = $this->jalankan(fn () => $this->timer->lanjutkan($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak dilanjutkan.');
    }

    public function reset(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $round = $this->jalankan(fn () => $this->timer->reset($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', 'Babak direset.');
    }

    public function selesaikanBabak(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $round = $this->jalankan(fn () => $this->timer->selesaikanBabak($this->babakAktifAtauGagal($match)));
        $this->siarkan(fn () => TimerTicked::dispatch($round));

        return $this->respond($request, $match, 'success', "Babak {$round->round} diselesaikan.");
    }

    /** Mengakhiri partai dengan sebab tertentu -- KO, mutlak, WMP, undur diri, cedera, WO, atau menang angka. */
    public function akhiri(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());

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
        $this->pastikanAparatPartai($match, $request->user());

        /*
         * Babak di luar jangkauan ditolak di validasi, bukan diterima lalu
         * dibalas peringatan. Babak 0 dan -1 sudah dijawab 422; babak 99
         * sama mustahilnya, jadi jawabannya harus sama — klien yang membaca
         * kode status tidak boleh menyimpulkan nilainya tercatat.
         */
        $jumlahBabak = $this->jumlahBabak($match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$jumlahBabak],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'jenis' => ['required', Rule::enum(JenisSerangan::class)],
        ], [
            'babak.max' => "Partai ini hanya punya {$jumlahBabak} babak.",
        ], [
            'babak' => 'Babak',
            'corner' => 'Sudut',
            'jenis' => 'Jenis serangan',
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
        $this->pastikanAparatPartai($match, $request->user());

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$this->jumlahBabak($match)],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'tingkat' => ['required', Rule::enum(TingkatPelanggaran::class)],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [], [
            'babak' => 'Babak',
            'corner' => 'Sudut',
            'tingkat' => 'Tingkat pelanggaran',
            'catatan' => 'Catatan',
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
        $this->pastikanAparatPartai($match, $request->user());

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$this->jumlahBabak($match)],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'hitungan' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'hitungan.max' => 'Hitungan wasit berhenti di 10.',
        ], [
            'babak' => 'Babak',
            'corner' => 'Sudut',
            'hitungan' => 'Hitungan',
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

        /*
         * Hasil yang sudah disahkan tidak bisa diubah lagi. Janji itu ditulis
         * di panel Dewan Wasit Juri, dicetak di berita acara, dan jadi dasar
         * kenapa bagan boleh maju ke tahap berikutnya -- tapi sampai sekarang
         * tidak ada yang menegakkannya di sisi server: nilai maupun hukuman
         * masih bisa dibatalkan setelah pengesahan.
         *
         * Koreksi sesudah pengesahan bukan tidak mungkin, tapi jalurnya protes
         * manajer (Pasal 15 ayat 4), bukan tombol Batalkan di panel.
         */
        if ($match->disahkan()) {
            throw ValidationException::withMessages([
                'match' => 'Hasil partai ini sudah disahkan, jadi nilai dan hukumannya tidak bisa diubah lagi. Koreksi hanya lewat protes manajer.',
            ]);
        }

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

        /*
         * Hasil yang sudah disahkan tidak bisa diubah lagi. Janji itu ditulis
         * di panel Dewan Wasit Juri, dicetak di berita acara, dan jadi dasar
         * kenapa bagan boleh maju ke tahap berikutnya -- tapi sampai sekarang
         * tidak ada yang menegakkannya di sisi server: nilai maupun hukuman
         * masih bisa dibatalkan setelah pengesahan.
         *
         * Koreksi sesudah pengesahan bukan tidak mungkin, tapi jalurnya protes
         * manajer (Pasal 15 ayat 4), bukan tombol Batalkan di panel.
         */
        if ($match->disahkan()) {
            throw ValidationException::withMessages([
                'match' => 'Hasil partai ini sudah disahkan, jadi nilai dan hukumannya tidak bisa diubah lagi. Koreksi hanya lewat protes manajer.',
            ]);
        }

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
    private function stateArray(SilatMatch $match, ?User $untuk = null): array
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
            'verifikasi' => $this->verifikasiArray($match, $untuk),
        ];
    }

    /**
     * Verifikasi juri yang sedang berjalan -- Pasal 13.
     *
     * # Kenapa disaring menurut siapa yang meminta
     *
     * Satu endpoint state melayani semua panel di gelanggang, panel juri
     * termasuk. Kalau jawaban tiap juri ikut dikirim apa adanya, juri yang
     * membuka panelnya akan melihat rekannya sudah menjawab "sudut merah",
     * lalu tidak lagi menjawab apa yang dilihatnya sendiri.
     *
     * Maka: juri partai ini hanya menerima SIAPA yang sudah menjawab, tanpa
     * jawabannya, selama pollingnya berjalan. Wasit, Ketua Pertandingan, dan
     * Dewan Wasit Juri menerima jawabannya -- mereka memang harus melihat
     * jawaban masuk satu per satu untuk tahu siapa yang masih ditunggu.
     *
     * Begitu polling ditutup, jawabannya terbuka untuk semua: tidak ada lagi
     * juri yang bisa terpengaruh, dan berita acara memang memuatnya.
     *
     * @return array<string, mixed>|null
     */
    private function verifikasiArray(SilatMatch $match, ?User $untuk): ?array
    {
        $verifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->with(['answers.judge:id,name', 'peminta:id,name'])
            ->latest('id')
            ->first();

        if ($verifikasi === null) {
            return null;
        }

        $bolehLihatJawaban = ! $verifikasi->berjalan() || ! $this->juriPartaiIni($match, $untuk);

        return [
            'id' => $verifikasi->id,
            'round' => $verifikasi->round,
            'jenis' => $verifikasi->jenis->value,
            'pertanyaan' => $verifikasi->jenis->pertanyaan(),
            'pilihan_tidak_ada' => $verifikasi->jenis->pilihanTidakAda(),
            'tingkat_pelanggaran' => $verifikasi->tingkat_pelanggaran?->value,
            'tingkat_pelanggaran_label' => $verifikasi->tingkat_pelanggaran?->label(),
            'status' => $verifikasi->status,
            'berjalan' => $verifikasi->berjalan(),
            'diminta_at' => $verifikasi->diminta_at?->toIso8601String(),
            /*
             * Pasal 13 menyebut verifikasi datang dari Ketua Pertandingan
             * maupun Wasit. Panel juri menyebutkan yang mana -- juri yang
             * ditanya berhak tahu siapa yang menghentikan pertandingan, dan
             * dua jabatan itu punya bobot berbeda di gelanggang.
             */
            'diminta_oleh' => $verifikasi->peminta?->name,
            'hasil' => $verifikasi->hasil?->value,
            'hasil_label' => $verifikasi->hasil?->label(),
            'sudah_diterapkan' => $verifikasi->sudahDiterapkan(),
            'akibat' => $verifikasi->hasil ? $this->polling->akibat($verifikasi) : null,
            'ambang' => $match->bracket->weightClass->tournament->peraturan()->ambang_sepakat,
            'jumlah_juri' => $this->polling->jumlahJuri($verifikasi),
            'hitungan' => $bolehLihatJawaban ? $this->polling->hitungan($verifikasi) : null,
            'jawaban' => $verifikasi->answers->sortBy('judge_number')->values()->map(fn ($j) => [
                'judge_user_id' => $j->judge_user_id,
                'judge_number' => $j->judge_number,
                'judge_name' => $j->judge?->name,
                'sebutan' => $j->sebutan(),
                // Yang disembunyikan cuma ini. Siapa yang sudah menjawab tetap
                // terlihat -- itu tidak menggiring siapa pun.
                'jawaban' => $bolehLihatJawaban ? $j->jawaban->value : null,
                'jawaban_label' => $bolehLihatJawaban ? $j->jawaban->label() : null,
                'server_ts' => $j->server_ts?->toIso8601String(),
            ]),
            'menunggu' => $this->polling->belumMenjawab($verifikasi)->map(fn ($o) => [
                'judge_user_id' => $o->user_id,
                'judge_number' => $o->number,
                'sebutan' => $o->sebutan(),
            ])->values(),
        ];
    }

    /** Apakah pengguna ini juri yang ditugaskan di partai ini. */
    private function juriPartaiIni(SilatMatch $match, ?User $untuk): bool
    {
        if ($untuk === null) {
            return false;
        }

        return MatchOfficial::query()
            ->where('match_id', $match->id)
            ->where('user_id', $untuk->id)
            ->where('role', MatchOfficial::ROLE_JURI)
            ->exists();
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
        /*
         * Sebutan aparat dipetakan sekali di sini, bukan di-query per baris.
         * Panel dewan juri sanggup menampilkan 60 baris sekaligus; menanyakan
         * nama tiap penekan satu per satu akan jadi puluhan query untuk satu
         * halaman yang dibuka justru saat pertandingan sedang disengketakan.
         */
        $sebutan = $match->officials()->with('user:id,name')->get()
            ->mapWithKeys(fn (MatchOfficial $o) => [$o->user_id => $o->sebutan()]);

        /*
         * Nilai dan hukuman yang lahir dari verifikasi juri tidak punya
         * judge_inputs -- tidak ada juri yang menekan tombolnya. Tanpa
         * penandaan ini, riwayat menampilkan jatuhan +3 yang seolah muncul
         * sendiri tanpa satu pun penekan, dan itu justru baris yang paling
         * dipersoalkan saat hasilnya digugat.
         */
        $dariVerifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->where('status', JudgeVerification::SELESAI)
            ->get(['id', 'score_event_id', 'penalty_id']);

        $verifikasiNilai = $dariVerifikasi->whereNotNull('score_event_id')->pluck('id', 'score_event_id');
        $verifikasiHukuman = $dariVerifikasi->whereNotNull('penalty_id')->pluck('id', 'penalty_id');

        $nilai = $match->scoreEvents()->berlaku()->with('judgeInputs:id,score_event_id,judge_user_id')
            ->latest('id')->limit(30)->get()->map(fn ($s) => [
                'tipe' => 'nilai',
                'id' => $s->id,
                'round' => $s->round,
                'corner' => $s->corner->value,
                'label' => "{$s->point_type->label()} ({$s->value})",
                'waktu' => $s->server_ts->toIso8601String(),
                'verifikasi_id' => $verifikasiNilai[$s->id] ?? null,
                // Urut supaya "Juri 1, Juri 3" tidak berganti-ganti urutan tiap resync.
                'oleh' => isset($verifikasiNilai[$s->id])
                    ? 'Verifikasi juri'
                    : ($s->judgeInputs
                        ->map(fn ($i) => $sebutan[$i->judge_user_id] ?? null)
                        ->filter()->unique()->sort()->values()->implode(', ') ?: null),
            ]);

        $hukuman = $match->penalties()->berlaku()->with('pencatat:id,name')
            ->latest('id')->limit(30)->get()->map(fn ($p) => [
                'tipe' => 'hukuman',
                'id' => $p->id,
                'round' => $p->round,
                'corner' => $p->corner->value,
                'label' => "{$p->tier->label()} ".($p->points !== null ? $p->points : '(DQ)'),
                'waktu' => $p->created_at->toIso8601String(),
                'verifikasi_id' => $verifikasiHukuman[$p->id] ?? null,
                'oleh' => isset($verifikasiHukuman[$p->id])
                    ? 'Verifikasi juri'
                    : ($sebutan[$p->created_by] ?? $p->pencatat?->name),
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

    /** Jumlah babak yang berlaku untuk golongan usia partai ini. */
    private function jumlahBabak(SilatMatch $match): int
    {
        $kelas = $match->bracket->weightClass;

        return (int) $kelas->tournament->peraturan()
            ->babakUntuk($kelas->golongan_usia)['jumlah'];
    }

    /**
     * Wasit dan juri hanya berwenang atas partai yang ditugaskan kepada
     * mereka. Izin peran saja tidak cukup: dua gelanggang berjalan
     * bersamaan dengan aparat yang sama-sama punya izin menilai, dan
     * aparat gelanggang sebelah tidak boleh ikut menilai atau menghukum
     * di sini.
     *
     * Peran tingkat kejuaraan -- Dewan Wasit Juri, Ketua Pertandingan --
     * sengaja tidak ikut aturan ini. Kewenangan mereka memang lintas
     * gelanggang, jadi mereka tidak pernah muncul di match_officials.
     * Yang diperiksa hanya peran yang memang ditugaskan per partai.
     */
    private function pastikanAparatPartai(SilatMatch $match, ?User $user): void
    {
        if ($user === null) {
            abort(403);
        }

        $peranPerPartai = [
            'juri' => MatchOfficial::ROLE_JURI,
            'wasit' => MatchOfficial::ROLE_WASIT,
        ];

        /*
         * Cukup ditugaskan dalam SALAH SATU kapasitas, bukan setiap kapasitas
         * yang perannya izinkan. Satu akun boleh memegang wasit sekaligus
         * juri; menuntut keduanya akan menolak wasit yang kebetulan juga
         * berperan juri di partai yang justru ditugaskan kepadanya.
         */
        $kapasitas = array_values(array_intersect_key(
            $peranPerPartai,
            array_flip($user->getRoleNames()->all()),
        ));

        if ($kapasitas !== []) {
            abort_unless(
                MatchOfficial::query()
                    ->where('match_id', $match->id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', $kapasitas)
                    ->exists(),
                403,
                'Anda tidak ditugaskan sebagai aparat pada partai ini.',
            );
        }

        /*
         * Operator terikat gelanggang, bukan partai: ia memegang satu
         * gelanggang sepanjang hari, jadi penugasannya ikut berlaku untuk
         * partai yang baru dijadwalkan ke sana kemudian.
         *
         * Partai yang belum punya gelanggang belum bisa dioperasikan
         * siapa pun -- jadwalkan dulu, baru ada operatornya.
         */
        if ($user->hasRole('operator-it')) {
            abort_unless(
                $match->arena_id !== null && DB::table('arena_operators')
                    ->where('arena_id', $match->arena_id)
                    ->where('user_id', $user->id)
                    ->exists(),
                403,
                'Anda bukan operator gelanggang tempat partai ini dimainkan.',
            );
        }
    }
}
