<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JawabanVerifikasi;
use App\Enums\JenisKelamin;
use App\Enums\JenisVerifikasi;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeVerification;
use App\Models\MatchOfficial;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\PollingVerifikasi;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
    ]);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->wasit = ($this->buatUser)('wasit');
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->wasit->id, 'role' => MatchOfficial::ROLE_WASIT,
    ]);

    // Tiga juri, sesuai jumlah juri Tanding.
    $this->juri = collect(range(1, 3))->map(function (int $nomor) {
        $user = ($this->buatUser)('juri');
        MatchOfficial::create([
            'match_id' => $this->match->id, 'user_id' => $user->id,
            'role' => MatchOfficial::ROLE_JURI, 'number' => $nomor,
        ]);

        return $user;
    });

    $this->polling = app(PollingVerifikasi::class);

    $this->minta = fn (JenisVerifikasi $jenis = JenisVerifikasi::Jatuhan, ?TingkatPelanggaran $tingkat = null) => $this->polling->minta(
        $this->match, $this->wasit, 1, $jenis, $tingkat,
    );
});

/*
 * --------------------------------------------------------------------
 * Ambang dan hasil
 * --------------------------------------------------------------------
 */

it('menetapkan hasil begitu dua juri sepakat, tanpa menunggu juri ketiga', function () {
    $verifikasi = ($this->minta)();

    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    expect($verifikasi->fresh()->hasil)->toBeNull();

    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);

    expect($verifikasi->hasil)->toBe(JawabanVerifikasi::Merah)
        ->and($verifikasi->hasil_at)->not->toBeNull();
});

it('tidak menggeser hasil yang sudah bulat ketika juri ketiga menjawab berbeda', function () {
    $verifikasi = ($this->minta)();

    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[2], JawabanVerifikasi::Biru);

    expect($verifikasi->hasil)->toBe(JawabanVerifikasi::Merah)
        ->and($verifikasi->answers)->toHaveCount(3);
});

it('memenangkan "tidak ada" ketika dua juri memilihnya', function () {
    /*
     * Ini yang membedakan verifikasi dari penilaian biasa. Dua juri yang
     * melihat jatuhan tidak sah harus mengalahkan satu juri yang menunjuk
     * sudut -- kalau "tidak ada" tidak dihitung, hasilnya justru kebalikan
     * dari yang dilihat mayoritas.
     */
    $verifikasi = ($this->minta)();

    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::TidakAda);
    $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[2], JawabanVerifikasi::TidakAda);

    expect($verifikasi->hasil)->toBe(JawabanVerifikasi::TidakAda);
});

it('memutuskan "tidak ada" ketika ketiga juri menjawab berbeda-beda', function () {
    $verifikasi = ($this->minta)();

    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Biru);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[2], JawabanVerifikasi::TidakAda);

    expect($verifikasi->hasil)->toBe(JawabanVerifikasi::TidakAda);
});

/*
 * --------------------------------------------------------------------
 * Siapa boleh menjawab, dan berapa kali
 * --------------------------------------------------------------------
 */

it('menolak jawaban dari juri yang tidak ditugaskan di partai ini', function () {
    $verifikasi = ($this->minta)();
    $orangLain = ($this->buatUser)('juri');

    expect(fn () => $this->polling->jawab($verifikasi, $orangLain, JawabanVerifikasi::Merah))
        ->toThrow(ValidationException::class);

    expect($verifikasi->fresh()->answers)->toHaveCount(0);
});

it('menolak jawaban kedua dari juri yang sama', function () {
    $verifikasi = ($this->minta)();

    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    expect(fn () => $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Biru))
        ->toThrow(ValidationException::class);

    expect($verifikasi->fresh()->answers)->toHaveCount(1);
});

it('menolak dua verifikasi berjalan sekaligus pada satu partai', function () {
    ($this->minta)();

    expect(fn () => ($this->minta)())->toThrow(ValidationException::class);
});

it('menolak verifikasi pada partai yang hasilnya sudah disahkan', function () {
    $this->match->update(['ratified_at' => now(), 'status' => SilatMatch::STATUS_SELESAI]);

    expect(fn () => ($this->minta)())->toThrow(ValidationException::class);
});

it('mewajibkan tingkat pelanggaran saat yang ditanyakan adalah pelanggaran', function () {
    /*
     * Tanpa tingkatnya, akibat verifikasi tidak bisa dinyatakan sebelum
     * diterapkan -- dan menyodorkan pilihan sanksi setelah juri terlanjur
     * menjawab berarti keputusan kedua yang tidak pernah mereka lihat.
     */
    expect(fn () => ($this->minta)(JenisVerifikasi::Pelanggaran))
        ->toThrow(ValidationException::class);

    $verifikasi = ($this->minta)(JenisVerifikasi::Pelanggaran, TingkatPelanggaran::Sedang);

    expect($verifikasi->tingkat_pelanggaran)->toBe(TingkatPelanggaran::Sedang);
});

/*
 * --------------------------------------------------------------------
 * Penerapan hasil
 * --------------------------------------------------------------------
 */

/*
 * Verifikasi jatuhan TIDAK menerbitkan nilai.
 *
 * Nilai mutlak jatuhan bukan penilaian yang dikonsensuskan tiga juri,
 * melainkan keputusan Dewan Wasit Juri. Yang dihasilkan polling ini adalah
 * jawaban juri atas pertanyaan wasit -- masukan yang dibaca dewan sebelum
 * menekan sudutnya, dan yang tetap tercatat supaya pelatih bisa memprotes
 * jawabannya sesuai Pasal 15.
 */
it('tidak menerbitkan nilai dari polling jatuhan', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);

    $verifikasi = $this->polling->terapkan($verifikasi, $this->wasit);

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0)
        ->and($verifikasi->score_event_id)->toBeNull()
        // Hasilnya tetap tercatat: yang hilang cuma penerbitan nilainya.
        ->and($verifikasi->hasil)->toBe(JawabanVerifikasi::Merah)
        ->and($verifikasi->status)->toBe(JudgeVerification::SELESAI);
});

it('menjatuhkan sanksi untuk sudut yang menang polling pelanggaran', function () {
    $verifikasi = ($this->minta)(JenisVerifikasi::Pelanggaran, TingkatPelanggaran::Sedang);
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Biru);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Biru);

    $verifikasi = $this->polling->terapkan($verifikasi, $this->wasit);

    expect($verifikasi->penalty_id)->not->toBeNull()
        ->and($verifikasi->penalty->corner)->toBe(Sudut::Biru)
        ->and(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

it('tidak menerbitkan apa pun ketika hasilnya "tidak ada"', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::TidakAda);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::TidakAda);

    $verifikasi = $this->polling->terapkan($verifikasi, $this->wasit);

    expect($verifikasi->score_event_id)->toBeNull()
        ->and($verifikasi->penalty_id)->toBeNull()
        ->and($verifikasi->status)->toBe(JudgeVerification::SELESAI)
        ->and(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

it('menolak penerapan sebelum ambang tercapai', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    expect(fn () => $this->polling->terapkan($verifikasi->fresh(), $this->wasit))
        ->toThrow(ValidationException::class);
});

/*
 * Diuji dengan pertanyaan PELANGGARAN, bukan jatuhan: hanya pelanggaran yang
 * menerbitkan akibatnya sendiri saat diterapkan, jadi hanya di sanalah
 * penerapan kedua kali bisa melahirkan sanksi ganda.
 */
it('menolak penerapan kedua kali', function () {
    $verifikasi = ($this->minta)(JenisVerifikasi::Pelanggaran, TingkatPelanggaran::Sedang);
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);

    $this->polling->terapkan($verifikasi, $this->wasit);

    expect(fn () => $this->polling->terapkan($verifikasi->fresh(), $this->wasit))
        ->toThrow(ValidationException::class);

    expect(Penalty::where('match_id', $this->match->id)->count())->toBe(1);
});

it('menyimpan verifikasi yang dibatalkan beserta jawaban yang sudah masuk', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    $verifikasi = $this->polling->batalkan($verifikasi, $this->wasit, 'Wasit salah memilih jenis pertanyaan.');

    expect($verifikasi->status)->toBe(JudgeVerification::DIBATALKAN)
        ->and($verifikasi->answers)->toHaveCount(1)
        ->and($verifikasi->catatan)->toBe('Wasit salah memilih jenis pertanyaan.')
        ->and(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

it('membuka lagi jalan untuk bertanya setelah verifikasi dibatalkan', function () {
    $verifikasi = ($this->minta)();
    $this->polling->batalkan($verifikasi, $this->wasit);

    expect(($this->minta)())->toBeInstanceOf(JudgeVerification::class);
});

/*
 * --------------------------------------------------------------------
 * Kerahasiaan jawaban -- syarat yang paling menentukan
 * --------------------------------------------------------------------
 */

it('menyembunyikan jawaban juri lain dari sesama juri selama polling berjalan', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    $state = $this->actingAs($this->juri[1])
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json('verifikasi');

    expect($state['jawaban'])->toHaveCount(1)
        // Siapa yang sudah menjawab boleh terlihat -- itu tidak menggiring.
        ->and($state['jawaban'][0]['judge_number'])->toBe(1)
        // Apa jawabannya tidak.
        ->and($state['jawaban'][0]['jawaban'])->toBeNull()
        ->and($state['hitungan'])->toBeNull();
});

it('menampilkan jawaban yang sudah masuk kepada wasit selagi polling berjalan', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    $state = $this->actingAs($this->wasit)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json('verifikasi');

    expect($state['jawaban'][0]['jawaban'])->toBe('red')
        ->and($state['hitungan']['red'])->toBe(1)
        ->and($state['menunggu'])->toHaveCount(2);
});

it('membuka jawaban untuk juri sekalipun setelah polling ditutup', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);
    $this->polling->terapkan($verifikasi, $this->wasit);

    $state = $this->actingAs($this->juri[2])
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json('verifikasi');

    expect($state['jawaban'][0]['jawaban'])->toBe('red')
        ->and($state['hitungan']['red'])->toBe(2);
});

/*
 * --------------------------------------------------------------------
 * Jalur HTTP dan izin peran
 * --------------------------------------------------------------------
 */

it('mengizinkan wasit membuka verifikasi lewat HTTP', function () {
    $this->actingAs($this->wasit)
        ->postJson(route('admin.turnamen.partai.verifikasi.minta', [$this->tournament, $this->match]), [
            'babak' => 1,
            'jenis' => 'jatuhan',
        ])
        ->assertOk();

    expect(JudgeVerification::where('match_id', $this->match->id)->count())->toBe(1);
});

it('melarang juri membuka verifikasi sendiri', function () {
    $this->actingAs($this->juri[0])
        ->postJson(route('admin.turnamen.partai.verifikasi.minta', [$this->tournament, $this->match]), [
            'babak' => 1,
            'jenis' => 'jatuhan',
        ])
        ->assertForbidden();

    expect(JudgeVerification::where('match_id', $this->match->id)->count())->toBe(0);
});

it('melarang wasit menjawab verifikasi seolah-olah juri', function () {
    $verifikasi = ($this->minta)();

    $this->actingAs($this->wasit)
        ->postJson(route('admin.turnamen.partai.verifikasi.jawab', [$this->tournament, $this->match, $verifikasi]), [
            'jawaban' => 'red',
        ])
        ->assertForbidden();
});

it('menolak verifikasi milik partai lain', function () {
    $verifikasi = ($this->minta)();

    $partaiLain = SilatMatch::create([
        'bracket_id' => $this->match->bracket_id, 'round' => 1, 'position' => 2,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->actingAs($this->juri[0])
        ->postJson(route('admin.turnamen.partai.verifikasi.jawab', [$this->tournament, $partaiLain, $verifikasi]), [
            'jawaban' => 'red',
        ])
        ->assertNotFound();
});

/*
 * --------------------------------------------------------------------
 * Jejak: riwayat panel dan berita acara
 * --------------------------------------------------------------------
 */

it('tidak memunculkan baris nilai dari polling jatuhan di riwayat panel', function () {
    /*
     * Jawaban juri atas pertanyaan jatuhan berhenti sebagai masukan. Kalau ia
     * tetap menerbitkan nilai sendiri, dewan yang kemudian menekan sudutnya
     * akan melahirkan +3 kedua untuk satu jatuhan yang sama.
     */
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Merah);
    $this->polling->terapkan($verifikasi, $this->wasit);

    $riwayat = $this->actingAs($this->wasit)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json('riwayat');

    expect(collect($riwayat)->firstWhere('tipe', 'nilai'))->toBeNull()
        ->and(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

it('mencatat verifikasi beserta jawaban tiap juri di berita acara', function () {
    /*
     * Pasal 15 membolehkan pelatih memprotes keputusan verifikasi, dan yang
     * diprotes adalah jawaban juri. Berita acara yang cuma memuat hasil
     * akhirnya membuat protes itu tidak bisa disusun.
     */
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $this->polling->jawab($verifikasi, $this->juri[1], JawabanVerifikasi::Biru);
    $verifikasi = $this->polling->jawab($verifikasi, $this->juri[2], JawabanVerifikasi::Merah);
    $this->polling->terapkan($verifikasi, $this->wasit);

    $html = view('admin.rekap.berita-acara', [
        'match' => $this->match->load(['red.athletes', 'blue.athletes', 'bracket.weightClass.tournament', 'rounds', 'officials.user']),
        'rounds' => collect(),
        'skorTotal' => ['merah' => 3, 'biru' => 0],
        'nilai' => collect(),
        'hukuman' => collect(),
        'penekan' => collect(),
        'pencatat' => collect(),
        'verifikasi' => JudgeVerification::where('match_id', $this->match->id)
            ->with(['answers' => fn ($q) => $q->orderBy('judge_number'), 'peminta:id,name'])->get(),
        'peraturan' => 1,
    ])->render();

    expect($html)
        ->toContain('Verifikasi Juri (Pasal 13)')
        ->toContain('Sudut mana yang menjatuhkan?')
        // Jawaban tiap juri, satu per satu -- termasuk yang kalah suara.
        ->toContain('Juri 1: Sudut merah')
        ->toContain('Juri 2: Sudut biru')
        ->toContain('Juri 3: Sudut merah')
        ->toContain('Jawaban juri dicatat sebagai masukan Dewan Wasit Juri');
});

it('mencatat verifikasi yang dibatalkan di berita acara', function () {
    $verifikasi = ($this->minta)();
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);
    $this->polling->batalkan($verifikasi, $this->wasit, 'Wasit salah memilih jenis pertanyaan.');

    $html = view('admin.rekap.berita-acara', [
        'match' => $this->match->load(['red.athletes', 'blue.athletes', 'bracket.weightClass.tournament', 'rounds', 'officials.user']),
        'rounds' => collect(),
        'skorTotal' => ['merah' => 0, 'biru' => 0],
        'nilai' => collect(),
        'hukuman' => collect(),
        'penekan' => collect(),
        'pencatat' => collect(),
        'verifikasi' => JudgeVerification::where('match_id', $this->match->id)
            ->with(['answers' => fn ($q) => $q->orderBy('judge_number'), 'peminta:id,name'])->get(),
        'peraturan' => 1,
    ])->render();

    expect($html)->toContain('Dibatalkan — Wasit salah memilih jenis pertanyaan.');
});
