<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormatJurus;
use App\Events\Jurus\PenampilanJurusBerubah;
use App\Http\Controllers\Controller;
use App\Models\JurusBattle;
use App\Models\JurusDeduction;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Tournament;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Jurus\JurusScoreCalculator;
use App\Support\Jurus\JurusTimer;
use App\Support\Jurus\PerbandinganBattle;
use App\Support\Jurus\PutuskanBattle;
use App\Support\Jurus\StatePenampilan;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lapisan HTTP mesin scoring Jurus. Sejajar dengan PartaiScoringController,
 * tapi jauh lebih ramping -- satu penampilan berjalan sekali dari awal sampai
 * selesai, tanpa babak dan tanpa konsensus real-time, jadi tidak ada
 * window/threshold untuk dijaga di sini.
 *
 * Yang MEMANG dimiliki Jurus, dan sempat disangkal keterangan lama di berkas
 * ini: sudut merah dan biru, serta bagan gugur -- pada nomor berformat
 * `battle` (Pasal 12.1.b.1). Yang tidak dimilikinya cuma babak dan konsensus.
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
            ->urutGolonganUsia()->orderBy('sort_order')
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
            /*
             * Battle nomor ini, kalau formatnya memang sistem gugur.
             *
             * Nomor berformat penampilan mendapat koleksi kosong dan daftarnya
             * tidak digambar sama sekali -- bukan daftar kosong berjudul
             * "Battle", yang membuat panitia mengira ada yang belum tersusun.
             */
            'battles' => $jurusEvent->format->pakaiBagan()
                ? $jurusEvent->bagan?->battles()
                    ->with('red.athletes', 'blue.athletes', 'performances')
                    ->orderBy('round')->orderBy('position')->get()
                    ?? collect()
                : collect(),
            // Dipakai kalimat kartu bagan; withCount tidak ikut di query ini.
            'pesertaSah' => app(SusunBaganJurus::class)->pesertaSah($jurusEvent)->count(),
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

    /**
     * Mengubah format sebuah nomor Jurus.
     *
     * Naskah 2025 hanya mengenal sistem gugur (Pasal 12.1.b.1), tapi kolomnya
     * berbawaan `penampilan` supaya kejuaraan yang sudah tersusun tidak berubah
     * bentuk di tengah jalan. Panitia yang memilihnya, bukan migrasi.
     */
    public function ubahFormat(Request $request, Tournament $tournament, JurusEvent $jurusEvent): RedirectResponse
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $data = $request->validate(['format' => ['required', Rule::enum(FormatJurus::class)]]);
        $format = FormatJurus::from($data['format']);

        /*
         * Nomor yang sudah punya penampilan tidak boleh berpindah format.
         *
         * Penampilan berformat lama tidak punya battle maupun sudut; membiarkan
         * formatnya berubah berarti peringkat median dan bagan gugur
         * memperebutkan baris yang sama, dan hasil yang sudah tercatat jadi
         * tidak bisa dibaca oleh keduanya.
         */
        if ($format !== $jurusEvent->format && $jurusEvent->performances()->exists()) {
            throw ValidationException::withMessages([
                'format' => 'Nomor ini sudah punya penampilan. Hapus penampilannya lebih dulu sebelum mengubah format.',
            ]);
        }

        $jurusEvent->update(['format' => $format]);

        return back()->with('success', "Format {$jurusEvent->nama()} diubah jadi {$format->label()}.");
    }

    /**
     * Menyusun bagan gugur satu nomor berformat battle.
     *
     * Penampilan untuk battle yang KEDUA sudutnya sudah terisi ikut dibuat di
     * sini -- itu seluruh ronde pertama. Ronde berikutnya menyusul sendiri
     * begitu pemenangnya naik, karena sebelum itu sudutnya memang belum ada
     * orangnya.
     */
    public function susunBagan(Request $request, Tournament $tournament, JurusEvent $jurusEvent): RedirectResponse
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $data = $request->validate(['acak' => ['sometimes', 'boolean']]);

        $susun = app(SusunBaganJurus::class);

        $bagan = $this->jalankan(fn () => $susun->untukNomor($jurusEvent, (bool) ($data['acak'] ?? true)));

        $dibuat = 0;

        foreach ($bagan->battles()->whereNotNull('red_registration_id')->whereNotNull('blue_registration_id')->get() as $battle) {
            $dibuat += $susun->siapkanPenampilan($battle)->count();
        }

        return back()->with('success', "Bagan tersusun: {$bagan->size} tempat, {$dibuat} penampilan dibuat.");
    }

    /**
     * Membuat dua penampilan untuk satu battle yang sudutnya sudah lengkap.
     *
     * Dipakai ronde lanjutan: begitu pemenang naik, battle berikutnya baru
     * punya dua nama untuk ditampilkan.
     */
    public function siapkanPenampilanBattle(Tournament $tournament, JurusBattle $jurusBattle): RedirectResponse
    {
        $this->pastikanMilikBattle($tournament, $jurusBattle);

        if ($jurusBattle->red_registration_id === null || $jurusBattle->blue_registration_id === null) {
            throw ValidationException::withMessages([
                'battle' => 'Battle ini belum punya dua sudut. Selesaikan dulu battle sebelumnya.',
            ]);
        }

        $penampilan = app(SusunBaganJurus::class)->siapkanPenampilan($jurusBattle);

        $this->siarkanBattle($jurusBattle, 'penampilan');

        return back()->with('success', "{$penampilan->count()} penampilan disiapkan untuk battle {$jurusBattle->id}.");
    }

    /**
     * Menetapkan pemenang battle dari skor akhir, lalu menaikkannya ke ronde
     * berikutnya -- Pasal 12.1.f.
     */
    public function putuskanBattle(Request $request, Tournament $tournament, JurusBattle $jurusBattle): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikBattle($tournament, $jurusBattle);

        /*
         * Pilihan sudut hanya sah saat skor akhirnya SERI, dan penjagaan itu
         * tegak di PutuskanBattle -- bukan di sini. Yang dijaga di sini cuma
         * bentuknya: sudut yang dipilih harus salah satu peserta battle, dan
         * alasan wajib menyertainya. Alasan yang kosong berarti berita acara
         * memuat keputusan tanpa dasar tertulis, dan itu yang ditanyakan
         * pelatih yang mengangkat kartu protes.
         */
        $data = $request->validate([
            'pemenang_registration_id' => [
                'nullable', 'integer',
                Rule::in(array_filter([$jurusBattle->red_registration_id, $jurusBattle->blue_registration_id])),
            ],
            'alasan' => ['nullable', 'string', 'max:255', 'required_with:pemenang_registration_id'],
        ], attributes: [
            'pemenang_registration_id' => 'Sudut pemenang',
            'alasan' => 'Alasan keputusan',
        ]);

        $this->jalankan(fn () => app(PutuskanBattle::class)(
            $jurusBattle,
            pemenangRegistrationId: $data['pemenang_registration_id'] ?? null,
            alasan: $data['alasan'] ?? null,
            oleh: $request->user(),
        ));

        $this->siarkanBattle($jurusBattle, 'keputusan');

        if ($request->expectsJson()) {
            return response()->json(app(PerbandinganBattle::class)($jurusBattle->refresh()));
        }

        return back()->with('success', "Pemenang battle {$jurusBattle->id} ditetapkan.");
    }

    /**
     * Perbandingan nilai kedua sudut satu battle -- yang dibaca sesudah
     * pertandingan selesai.
     *
     * Skor akhir Jurus adalah median enam juri dikurangi pengurangan. "9.72
     * lawan 9.70" tidak menjelaskan apa pun sampai pembacanya tahu apakah
     * bedanya datang dari penilaian juri atau dari satu pengurangan 0.50 yang
     * dijatuhkan Pengawas -- dan pelatih yang mengangkat kartu protes
     * menanyakan persis itu.
     */
    public function battle(Tournament $tournament, JurusBattle $jurusBattle): View
    {
        $this->pastikanMilikBattle($tournament, $jurusBattle);

        return view('jurus.battle', [
            'tournament' => $tournament,
            'battle' => $jurusBattle,
            'config' => [
                // Channel siaran halaman ini. Tiap penampilan battle menyiarkan
                // ke sini juga, jadi satu langganan cukup untuk kedua sudut.
                'battleId' => $jurusBattle->id,
                'state' => route('admin.turnamen.jurus.battle.state', [$tournament, $jurusBattle]),
                'putuskan' => route('admin.turnamen.jurus.battle.putuskan', [$tournament, $jurusBattle]),
            ],
        ]);
    }

    public function battleState(Tournament $tournament, JurusBattle $jurusBattle): JsonResponse
    {
        $this->pastikanMilikBattle($tournament, $jurusBattle);

        return response()->json(app(PerbandinganBattle::class)($jurusBattle));
    }

    private function pastikanMilikBattle(Tournament $tournament, JurusBattle $battle): void
    {
        abort_unless(
            $battle->bracket->jurusEvent->tournament_id === $tournament->id,
            404,
        );
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

        // Muatannya tinggal di App\Support\Jurus\StatePenampilan: panel Jurus
        // per gelanggang menjawab pertanyaan yang sama lewat alamat lain, dan
        // dua salinan muatan adalah dua salinan yang bisa berbeda diam-diam.
        return response()->json(app(StatePenampilan::class)($performance));
    }

    public function mulaiTimer(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $this->jalankan(fn () => $this->timer->mulai($performance));
        $this->siarkan($performance, 'timer');

        return $this->respond($request, 'success', 'Penampilan dimulai.');
    }

    public function berhentiTimer(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $this->jalankan(fn () => $this->timer->berhenti($performance));
        $this->siarkan($performance, 'timer');

        return $this->respond($request, 'success', 'Penampilan diselesaikan.');
    }

    /** Juri mengirim (atau memperbarui) nilai akhirnya sendiri untuk penampilan ini. */
    public function nilai(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);

        /*
         * Langkah skalanya ikut ditegakkan, bukan cuma didefinisikan.
         * Tanpa ini nilai 9.876543210987 dijawab "Nilai tersimpan." lalu
         * diam-diam dibulatkan jadi 9.88 oleh kolomnya -- juri membaca
         * konfirmasi berhasil untuk angka yang bukan angka yang tersimpan.
         */
        $skala = config('scoring.jurus.skala');
        $langkah = (float) $skala['langkah'];

        $data = $request->validate([
            'value' => [
                'required', 'numeric', "min:{$skala['min']}", "max:{$skala['max']}",
                function (string $atribut, mixed $nilai, Closure $gagal) use ($langkah) {
                    // Dikalikan dulu supaya perbandingannya bulat: fmod pada
                    // pecahan biner menyisakan galat yang menolak nilai sah.
                    $kelipatan = round((float) $nilai / $langkah);

                    if (abs($kelipatan * $langkah - (float) $nilai) > 1e-9) {
                        $gagal('Nilai Jurus memakai kelipatan 0,01 — paling banyak dua angka di belakang koma.');
                    }
                },
            ],
        ], attributes: ['value' => 'Nilai']);

        JurusScore::updateOrCreate(
            ['performance_id' => $performance->id, 'judge_user_id' => $request->user()->id],
            ['value' => $data['value']],
        );

        $this->siarkan($performance, 'nilai');

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

        $this->siarkan($performance, 'pengurangan');

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

        $this->siarkan($performance, 'pengurangan');

        return $this->respond($request, 'success', 'Pengurangan 0.50 dicatat.');
    }

    public function batalkanPengurangan(Request $request, Tournament $tournament, JurusPerformance $performance, JurusDeduction $deduction): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        abort_unless($deduction->performance_id === $performance->id, 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']]);

        $deduction->update(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $data['alasan']]);

        $this->siarkan($performance, 'pengurangan');

        return $this->respond($request, 'warning', 'Pengurangan dibatalkan.');
    }

    /** Pengawas/Dewan Wasit Juri menetapkan diskualifikasi -- skor akhirnya jadi 0,00 (Pasal 12.1.e.4.h). */
    public function diskualifikasi(Request $request, Tournament $tournament, JurusPerformance $performance): RedirectResponse|JsonResponse
    {
        $this->pastikanMilikPerforma($tournament, $performance);
        $performance->update(['didiskualifikasi' => true]);
        $this->siarkan($performance, 'diskualifikasi');

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

        $this->siarkan($performance, 'sahkan');

        return $this->respond($request, 'success', 'Skor penampilan disahkan.');
    }

    /** @return array<string, mixed> */
    private function konfigPanel(Tournament $tournament, JurusPerformance $performance): array
    {
        return [
            'performanceId' => $performance->id,
            /*
             * Channel battle ikut dikirim supaya panel penampilan yang berdiri
             * di dalam battle mendengar perubahan LAWANNYA juga -- itulah yang
             * membuat papan perbandingan dan tombol "Tetapkan pemenang" tidak
             * pernah membaca satu sisi yang basi.
             */
            'battleId' => $performance->jurus_battle_id,
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

    /**
     * Menerbitkan siaran perubahan satu penampilan.
     *
     * Dipanggil SESUDAH perubahannya tersimpan, dan sengaja di controller,
     * bukan di dalam JurusTimer atau model: penerbitannya mengikuti aksi
     * petugas yang berhasil, bukan tiap penulisan kolom. Penampilan dibaca
     * ulang dari basis data supaya `jurus_battle_id` yang baru saja berubah
     * (mis. saat penampilan battle disiapkan) ikut menentukan channel-nya.
     */
    private function siarkan(JurusPerformance $performance, string $sebab): void
    {
        PenampilanJurusBerubah::dispatch($performance->fresh(), $sebab);
    }

    /**
     * Menyiarkan perubahan satu battle lewat kedua penampilannya.
     *
     * Battle tidak punya event sendiri: yang berubah selalu bisa dibaca dari
     * penampilan, dan halaman perbandingan sudah mendengarkan channel battle
     * yang ikut dibawa tiap penampilan. Battle tanpa penampilan -- bye, atau
     * ronde yang sudutnya belum lengkap -- tidak menyiarkan apa pun, dan
     * memang tidak ada yang menunggunya.
     */
    private function siarkanBattle(JurusBattle $battle, string $sebab): void
    {
        foreach ($battle->performances()->get() as $penampilan) {
            PenampilanJurusBerubah::dispatch($penampilan, $sebab);
        }
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
