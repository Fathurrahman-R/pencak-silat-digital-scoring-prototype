<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Events\Scoring\MatchStateChanged;
use App\Events\Scoring\PenaltyIssued;
use App\Events\Scoring\TimerTicked;
use App\Http\Controllers\Concerns\MenjagaAparatGelanggang;
use App\Http\Controllers\Controller;
use App\Models\JudgeVerification;
use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Arsip\PendorongArsip;
use App\Support\Panel\KonfigPanel;
use App\Support\Panel\StatePartaiPanel;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
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
    use MenjagaAparatGelanggang;

    public function __construct(
        private readonly MatchTimer $timer,
        private readonly TanggaHukuman $tangga,
        private readonly HitunganTeknik $hitungan,
        private readonly CatatInputJuri $catatInput,
        private readonly TandingScoreCalculator $kalkulator,
        private readonly PendorongArsip $arsip,
    ) {}

    /** Resync state penuh -- dipanggil tiap panel memuat ulang atau tersambung kembali. */
    public function state(Request $request, Tournament $tournament, SilatMatch $match): JsonResponse
    {
        $this->pastikanMilik($tournament, $match);

        return response()->json(app(StatePartaiPanel::class)($match, $request->user()));
    }

    public function operator(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, request()->user());

        return view('silat.papan', [
            'tournament' => $tournament,
            'match' => $match->load('bracket.weightClass'),
            'config' => $this->konfigPanel($tournament, $match),
        ]);
    }

    public function wasit(Tournament $tournament, SilatMatch $match): View|RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, request()->user());

        if ($alihkan = $this->alihkanKeGelanggang($tournament, $match, 'wasit')) {
            return $alihkan;
        }

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

    /*
     * Panelnya sendiri ikut dijaga, bukan hanya aksinya.
     *
     * Sebelum ini, juri gelanggang sebelah bisa membuka papan tombol partai
     * yang bukan tugasnya: halaman tampil utuh dengan nama kedua pesilat dan
     * keempat tombol menyala, dan penolakan baru muncul setelah ia menekan.
     * Di gelanggang, kekeliruan itu terbaca sebagai panel yang rusak, bukan
     * sebagai partai yang salah dibuka.
     */
    public function juri(Tournament $tournament, SilatMatch $match): View|RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, request()->user());

        if ($alihkan = $this->alihkanKeGelanggang($tournament, $match, 'juri')) {
            return $alihkan;
        }

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

        $nilai = $match->scoreEvents()->berlaku()
            ->with(['judgeInputs:id,score_event_id,judge_user_id', 'penerbit:id,name'])
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

        $penekan = $nilai->mapWithKeys(fn ($n) => [$n->id => $n->mutlak()
            // Nilai mutlak jatuhan tidak ditekan juri mana pun; yang tercatat
            // penerbitnya, supaya kolomnya tidak kosong di dokumen resmi.
            ? ($sebutan[$n->issued_by] ?? 'Wasit').($n->penerbit ? ' — '.$n->penerbit->name : '')
            : ($n->judgeInputs
                ->map(fn ($i) => $sebutan[$i->judge_user_id] ?? null)
                ->filter()->unique()->sort()->values()->implode(', ') ?: null)]);

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

    /**
     * Alamat resync + seluruh aksi, dikirim ke panel Alpine lewat @js(...).
     *
     * Isinya pindah ke App\Support\Panel\KonfigPanel karena panel
     * per-gelanggang harus bisa menghitung ulang alamat aksinya tiap kali
     * pengendali memindahkan jadwal. Bentuk keluarannya sengaja tidak berubah
     * sedikit pun -- panel per-partai membaca kunci yang sama seperti
     * sebelumnya.
     *
     * @return array<string, mixed>
     */
    private function konfigPanel(Tournament $tournament, SilatMatch $match): array
    {
        return app(KonfigPanel::class)(
            $tournament,
            $match,
            $match->arena,
            request()->user(),
            mode: 'partai',
        );
    }

    public function mulaiBabak(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());

        /*
         * Batas atasnya ikut divalidasi di sini, bukan cuma di MatchTimer.
         *
         * Tanpa `max`, permintaan babak 99 lolos validasi dan baru ditolak
         * oleh pemeriksaan "babak sebelumnya belum selesai" -- yang lalu
         * menjawab "Babak 98 belum selesai", kalimat yang menyesatkan siapa
         * pun yang membacanya di panel.
         */
        $jumlahBabak = $this->jumlahBabak($match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$jumlahBabak],
        ], [
            'babak.max' => "Partai ini hanya punya {$jumlahBabak} babak.",
        ], [
            'babak' => 'Babak',
        ]);

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

        /*
         * Pengesahan mengunci pemenang, bukan cuma angkanya.
         *
         * Pembatalan nilai dan hukuman sudah menolak partai yang disahkan,
         * tapi jalur ini dulu tidak: satu permintaan "Akhiri partai" lagi
         * masih menukar pemenang dan menyeret perubahannya naik ke bagan,
         * sementara stempel pengesahan lama tetap terpasang seolah tidak
         * terjadi apa-apa. Koreksi setelah pengesahan hanya lewat protes
         * manajer, sama seperti koreksi nilai.
         */
        $this->pastikanBelumDisahkan($match);

        $data = $request->validate([
            'corner' => ['required', Rule::enum(Sudut::class)],
            'sebab' => ['required', 'string', 'in:angka,teknik,mutlak,wmp,undur_diri,cedera,wo,berat_badan_teringan,nilai_terbanyak'],
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

        /*
         * Protes manajer yang diterima tapi akibatnya belum dijalankan menahan
         * pengesahan -- Pasal 15 ayat 4 huruf c.e.
         *
         * Tanpa ini, pemenang naik ke slot bagan berikutnya SEBELUM babak
         * tambahannya dimainkan. Bagan yang telanjur bergeser tidak bisa
         * ditarik kembali tanpa membatalkan partai-partai sesudahnya.
         */
        $menunggu = $match->managerProtests->first(fn ($protes) => $protes->akibatMenunggu());

        if ($menunggu !== null) {
            throw ValidationException::withMessages([
                'match' => "Protes manajer diterima dengan akibat “{$menunggu->akibat->label()}”, dan akibat itu belum dijalankan. "
                    .'Jalankan dulu sebelum hasilnya disahkan.',
            ]);
        }

        $match->update(['ratified_at' => now(), 'ratified_by' => $request->user()->id]);

        /*
         * Bukti partai dikirim ke node global begitu hasilnya sah.
         *
         * Di sini, bukan lewat pekerja antrean: mesin gelanggang tidak
         * menjalankan pekerja saat hari-H. Pengesahan terjadi sekali per
         * partai dan bukan jalur panas seperti tekanan tombol juri, jadi satu
         * perjalanan HTTP bertimeout pendek masih pantas -- dan imbalannya
         * besar: riwayat penekanan tombol mendarat di node arsip dalam
         * hitungan detik setelah gong terakhir, bukan menunggu ada yang ingat
         * menekan tombol.
         *
         * Kegagalannya ditelan PendorongArsip dan cuma meninggalkan baris
         * antrean. Hasil yang sudah diputuskan dewan juri tidak boleh batal
         * karena satu laptop tidak menjawab.
         */
        $this->arsip->antrekan($match->fresh());

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', 'Hasil partai disahkan.');
    }

    /**
     * Juri mengirim satu nilai -- pukulan atau tendangan.
     *
     * Jatuhan TIDAK diterima di sini. Nilainya mutlak: bukan penilaian yang
     * dikonsensuskan tiga juri, melainkan keputusan Dewan Wasit Juri, dan
     * jalannya lewat jatuhan() di bawah. Penolakannya berdiri di server, bukan
     * cuma berupa tombol yang tidak digambar -- panel juri berjalan di ponsel
     * yang tetap memegang halaman lamanya setelah aplikasi diperbarui.
     */
    public function nilai(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());

        /*
         * Jalur terpanas di seluruh sistem: tiga juri menekan beruntun, dan
         * tiap tekanan berjalan sendirian di server yang melayani satu
         * permintaan pada satu waktu. Rantai kelas-peraturan dan daftar aparat
         * dimuat sekali di sini lalu ikut sampai ke ConsensusEvaluator dan
         * siarannya, alih-alih ditelusuri ulang di tiap persinggahan.
         */
        $match->loadMissing(['bracket.weightClass.tournament.ruleSetting', 'officials']);

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
            /*
             * Dua aturan, bukan satu enum yang dipersempit: jenis yang tidak
             * dikenal dan jatuhan adalah dua kekeliruan berbeda dan berhak
             * atas pesan yang berbeda. Enum yang dipersempit menjawab keduanya
             * dengan kalimat yang sama, dan juri yang membacanya tidak tahu
             * apakah tombolnya rusak atau memang bukan haknya.
             */
            'jenis' => [
                'required',
                Rule::enum(JenisSerangan::class),
                Rule::notIn([JenisSerangan::Jatuhan->value]),
            ],
        ], [
            'babak.max' => "Partai ini hanya punya {$jumlahBabak} babak.",
            'jenis.not_in' => 'Jatuhan tidak dinilai juri — nilainya diterbitkan Dewan Wasit Juri.',
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

    /**
     * Wasit menerbitkan nilai mutlak jatuhan.
     *
     * Sederajat dengan hukuman, bukan dengan penilaian juri: nilainya mutlak,
     * dan yang memutuskan adalah orang yang berdiri di gelanggang dan melihat
     * jatuhnya. Karena itu ia dijaga wewenang yang sama dengan sanksi
     * (`hukuman` Create) dan mensyaratkan penekannya memang aparat partai ini
     * -- wasit gelanggang sebelah tidak menerbitkan nilai di sini.
     *
     * Tidak melewati verifikasi juri. Verifikasi baru dipakai kalau wasit
     * sendiri ragu sudut mana yang menjatuhkan; kalau ia melihatnya dengan
     * jelas, menahannya di belakang polling tiga juri hanya memperlambat
     * pertandingan atas sesuatu yang tidak dipersoalkan.
     *
     * Langsung terbit begitu sudutnya ditekan, tanpa dialog konfirmasi:
     * jatuhan diputuskan sementara pertandingan berjalan, dan satu dialog di
     * antara keputusan dan angkanya membuat papan skor tertinggal dari apa
     * yang sudah dilihat penonton. Salah tekan diperbaiki lewat pembatalan
     * nilai oleh Dewan Wasit Juri, jalur yang sama dengan pembatalan nilai
     * juri yang keliru.
     *
     * Bila wasit sempat ragu dan bertanya ke juri, `verifikasi_id` menautkan
     * jawaban itu ke nilai yang akhirnya terbit: berita acara lalu bisa
     * menunjukkan bahwa nilai ini diputuskan setelah menimbang jawaban juri.
     */
    public function jatuhan(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $this->pastikanBelumSelesai($match);
        $this->pastikanBabakMenerimaInput($match, (int) $request->input('babak'));

        $jumlahBabak = $this->jumlahBabak($match);

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$jumlahBabak],
            'corner' => ['required', Rule::enum(Sudut::class)],
            'verifikasi_id' => [
                // String, bukan integer: kunci judge_verifications sudah pindah
                // ke ULID. Aturan yang tertinggal di sini tidak melempar galat,
                // ia MENOLAK permintaan yang sah -- wasit menerbitkan jatuhan
                // hasil pertanyaan ke juri dan yang kembali cuma pesan validasi
                // tentang kolom yang tidak pernah ia isi sendiri.
                'nullable', 'string',
                Rule::exists('judge_verifications', 'id')->where('match_id', $match->id),
            ],
        ], [
            'babak.max' => "Partai ini hanya punya {$jumlahBabak} babak.",
        ], [
            'babak' => 'Babak',
            'corner' => 'Sudut',
        ]);

        $nilai = $match->bracket->weightClass->tournament->peraturan()
            ->nilaiUntuk(JenisSerangan::Jatuhan->value);

        $scoreEvent = ScoreEvent::create([
            'match_id' => $match->id,
            'round' => (int) $data['babak'],
            'corner' => Sudut::from($data['corner']),
            'point_type' => JenisSerangan::Jatuhan,
            'value' => $nilai,
            'server_ts' => now(),
            'issued_by' => $request->user()->id,
        ]);

        if (! empty($data['verifikasi_id'])) {
            JudgeVerification::whereKey($data['verifikasi_id'])
                ->whereNull('score_event_id')
                ->update(['score_event_id' => $scoreEvent->id]);
        }

        $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

        return $this->respond($request, $match, 'success', "Jatuhan +{$nilai} diterbitkan.");
    }

    /** Wasit menjatuhkan sanksi -- pembinaan, teguran, atau peringatan sesuai tingkat pelanggarannya. */
    public function hukuman(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $match);
        $this->pastikanAparatPartai($match, $request->user());
        $this->pastikanBelumSelesai($match);
        $this->pastikanBabakMenerimaInput($match, (int) $request->input('babak'));

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
        $this->pastikanBelumSelesai($match);
        $this->pastikanBabakMenerimaInput($match, (int) $request->input('babak'));

        $data = $request->validate([
            'babak' => ['required', 'integer', 'min:1', 'max:'.$this->jumlahBabak($match)],
            'serentak' => ['sometimes', 'boolean'],
            // Wajib kecuali hitungannya serentak -- yang serentak tidak punya
            // sudut, dan memaksanya menyebut satu berarti mengarang sudut yang
            // tidak diputuskan siapa pun.
            'corner' => [Rule::requiredIf(fn () => ! $request->boolean('serentak')), 'nullable', Rule::enum(Sudut::class)],
            'hitungan' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'hitungan.max' => 'Hitungan wasit berhenti di 10.',
        ], [
            'babak' => 'Babak',
            'corner' => 'Sudut',
            'hitungan' => 'Hitungan',
        ]);

        /*
         * Hitungan serentak BUKAN dua tekanan hitungan biasa -- Pasal 11.6.c
         * huruf b. Jalur satu sudut menjatuhkan Teguran di hitungan ke-9 dan
         * mengakhiri partai dengan pemenang di ke-10; keduanya keliru saat yang
         * jatuh adalah kedua pesilat, karena naskah justru menyuruh menimbang
         * berat badan atau menghitung nilai terbanyak. Karena itu ia punya
         * jalurnya sendiri, dan panel yang menawarkan penyelesaiannya.
         */
        if ($request->boolean('serentak')) {
            $this->jalankan(fn () => $this->hitungan->catatSerentak(
                $match,
                (int) $data['babak'],
                (int) $data['hitungan'],
                $request->user(),
            ));

            $this->siarkan(fn () => MatchStateChanged::dispatch($match->fresh()));

            return $this->respond($request, $match, 'success', 'Hitungan serentak tercatat untuk kedua sudut.');
        }

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
        } catch (QueryException $e) {
            /*
             * Dua panel yang menekan aksi yang sama dalam sepersekian detik
             * kalah di indeks unik, bukan di pemeriksaan PHP -- dan sebelum
             * ini pengecualiannya diteruskan apa adanya, sehingga operator
             * yang kalah cepat membaca nama tabel, nama indeks, dan seluruh
             * perintah INSERT di layarnya. Datanya sendiri tetap aman; yang
             * perlu diperbaiki hanya kalimat yang ia terima.
             */
            report($e);

            throw ValidationException::withMessages([
                'aksi' => 'Aksi ini baru saja dijalankan dari panel lain. Muat ulang panel untuk melihat keadaan terbaru.',
            ]);
        } catch (RuntimeException $e) {
            /*
             * Urutannya penting: QueryException adalah turunan PDOException,
             * yang turunan RuntimeException. Ditaruh setelah blok ini, ia
             * tidak pernah tercapai dan pesan SQL mentahnya tetap sampai ke
             * layar operator.
             */
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

    /**
     * Mengantar alamat per-partai yang masih beredar ke panel gelanggangnya.
     *
     * Tautan lama tidak pecah, tapi ia juga tidak boleh mendaratkan petugas di
     * partai yang sudah lewat. Alamat per-partai basi begitu pengendali
     * memindahkan jadwal; alamat gelanggang tidak pernah basi.
     *
     * Hanya berlaku untuk panel wasit dan juri. Dewan wasit juri dan keberatan
     * memang harus bisa membuka partai TERTENTU -- termasuk yang sudah selesai
     * -- untuk ditinjau, disahkan, dan dicetak berita acaranya.
     *
     * Partai yang belum dijadwalkan tetap dirender apa adanya: tidak ada
     * gelanggang untuk diikuti.
     */
    private function alihkanKeGelanggang(Tournament $tournament, SilatMatch $match, string $panel): ?RedirectResponse
    {
        if ($match->arena_id === null) {
            return null;
        }

        return redirect()->route("admin.turnamen.gelanggang.panel.{$panel}", [$tournament, $match->arena]);
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }

    /**
     * Hasil yang sudah disahkan tidak bisa diubah lagi -- baik angkanya
     * maupun pemenangnya. Koreksi sesudah pengesahan jalurnya protes manajer
     * (Pasal 15 ayat 4), bukan tombol di panel gelanggang.
     */
    private function pastikanBelumDisahkan(SilatMatch $match): void
    {
        if ($match->disahkan()) {
            throw ValidationException::withMessages([
                'match' => 'Hasil partai ini sudah disahkan, jadi nilai dan hukumannya tidak bisa diubah lagi. Koreksi hanya lewat protes manajer.',
            ]);
        }
    }

    /**
     * Partai yang sudah diakhiri tidak menerima kejadian baru.
     *
     * Timer dan nilai juri sudah menolaknya sejak awal, tapi hukuman,
     * jatuhan, dan hitungan wasit dulu tidak: satu tekanan tak sengaja di
     * panel wasit setelah gong masih mengubah skor akhir partai yang
     * pemenangnya sudah naik ke bagan. Pembatalan nilai tetap boleh --
     * itu memang jalur koreksi sebelum pengesahan.
     */
    /**
     * Wasit tidak boleh diam-diam menghukum babak berjalan saat susulan
     * terbuka.
     *
     * Simetris dengan penjagaan di CatatInputJuri. Tanpa ini, juri menekan
     * nilai ke babak 2 sementara wasit di sebelahnya menjatuhkan teguran ke
     * babak 3 -- dua orang di gelanggang yang sama mencatat kejadian ke babak
     * yang berbeda.
     */
    private function pastikanBabakMenerimaInput(SilatMatch $match, int $babak): void
    {
        if ($match->susulan_round !== null && $match->susulan_round !== $babak) {
            throw ValidationException::withMessages([
                'babak' => "Babak {$match->susulan_round} sedang dibuka untuk input susulan.",
            ]);
        }
    }

    private function pastikanBelumSelesai(SilatMatch $match): void
    {
        if ($match->selesai()) {
            throw ValidationException::withMessages([
                'match' => 'Partai ini sudah selesai — hukuman, jatuhan, dan hitungan tidak bisa ditambahkan lagi. Koreksi lewat pembatalan oleh Dewan Wasit Juri.',
            ]);
        }
    }

    /** Jumlah babak yang berlaku untuk golongan usia partai ini. */
    private function jumlahBabak(SilatMatch $match): int
    {
        $kelas = $match->bracket->weightClass;

        return (int) $kelas->tournament->peraturan()
            ->babakUntuk($kelas->golongan_usia)['jumlah'];
    }
}
