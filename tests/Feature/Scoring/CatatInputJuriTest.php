<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\StatusBabak;
use App\Enums\Sudut;
use App\Events\Scoring\JudgeInputReceived;
use App\Events\Scoring\ScoreAwarded;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\MatchRound;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\ConsensusEvaluator;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
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
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->juri = User::factory()->create();
    $this->catat = new CatatInputJuri(new ConsensusEvaluator);
});

it('menolak input saat babak belum pernah dimulai', function () {
    Event::fake([JudgeInputReceived::class, ScoreAwarded::class]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    /*
     * Alasannya kini membedakan "babaknya belum ada barisnya sama sekali" dari
     * "barisnya ada tapi timernya berhenti". Keduanya dulu dijawab kalimat yang
     * sama, dan yang membacanya di gelanggang tidak bisa tahu apakah wasit
     * belum menekan Mulai atau baru saja menekan Jeda.
     */
    expect($input->ditolak())->toBeTrue()
        ->and($input->rejected_reason)->toBe('Babak ini belum pernah dimulai.');

    Event::assertDispatched(JudgeInputReceived::class);
    Event::assertNotDispatched(ScoreAwarded::class);
});

it('menolak input saat babak sedang jeda', function () {
    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000, 'status' => StatusBabak::Jeda,
    ]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->ditolak())->toBeTrue()
        ->and($input->rejected_reason)->toContain('tidak berjalan');
});

it('menolak input yang menyasar babak selain babak yang sedang berjalan', function () {
    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 2, 'duration_ms' => 120_000, 'status' => StatusBabak::Berjalan,
    ]);

    // current_round partai masih 1, tapi input ditujukan ke babak 2.
    $input = ($this->catat)($this->match, $this->juri, 2, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->ditolak())->toBeTrue()
        ->and($input->rejected_reason)->toContain('bukan babak yang sedang berjalan');
});

it('menerima input dan menyiarkannya saat babak sedang berjalan', function () {
    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    Event::fake([JudgeInputReceived::class, ScoreAwarded::class]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->ditolak())->toBeFalse();

    Event::assertDispatched(JudgeInputReceived::class, fn ($e) => $e->input->is($input));
    Event::assertNotDispatched(ScoreAwarded::class); // baru satu juri, belum capai ambang
});

it('menyiarkan ScoreAwarded begitu ambang konsensus tercapai', function () {
    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    $juriKedua = User::factory()->create();

    ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    Event::fake([ScoreAwarded::class]);
    ($this->catat)($this->match, $juriKedua, 1, Sudut::Merah, JenisSerangan::Pukulan);

    Event::assertDispatched(ScoreAwarded::class);
});

/*
 * Siaran input juri sampai ke DUA channel: privat gelanggang dan publik
 * (overlay siaran memakainya untuk indikator J1..Jn). Karena Laravel mengirim
 * muatan yang sama ke setiap channel sebuah event, muatan itu tidak boleh
 * memuat apa pun yang tidak boleh dilihat penonton -- FR-H-04.
 */
it('menyiarkan input juri tanpa identitas juri sama sekali', function () {
    $this->match->update(['arena_id' => Arena::factory()->for($this->tournament)->create()->id]);

    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI,
        'number' => 2,
    ]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    $muatan = (new JudgeInputReceived($input->fresh()))->broadcastWith();

    expect($muatan)->toHaveKey('judge_number')
        ->and($muatan['judge_number'])->toBe(2)
        ->and($muatan)->not->toHaveKey('judge_id')
        ->and($muatan)->not->toHaveKey('judge_name')
        ->and($muatan)->not->toHaveKey('rejected_reason');
});

/*
 * Indikator "juri menekan" di panel dan overlay memadamkan dirinya sendiri
 * memakai angka ini. Kalau ia tidak ikut disiarkan, penerima terpaksa memakai
 * angka bawaannya sendiri -- dan turnamen yang menyetel jendela konsensus
 * berbeda akan menayangkan titik yang padam lebih cepat atau lebih lambat
 * daripada saat server benar-benar membuang tekanannya.
 */
it('menyiarkan tenggat kedaluwarsa sesuai setelan peraturan turnamen', function () {
    $this->tournament->peraturan()->update(['window_konsensus_ms' => 3500]);
    $this->tournament->unsetRelation('ruleSetting');

    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    $muatan = (new JudgeInputReceived($input->fresh()))->broadcastWith();

    expect($muatan['kedaluwarsa_ms'])->toBe(3500);
});

it('menyiarkan input juri ke channel publik dan privat sekaligus saat siaran menyala', function () {
    // Dinyatakan di sini, bukan diwarisi diam-diam dari phpunit.xml: bawaan
    // kedua saklar MATI, dan uji ini memang menguji keadaan menyala.
    config(['overlay.enabled' => true, 'live.enabled' => true]);

    $arena = Arena::factory()->for($this->tournament)->create();
    $this->match->update(['arena_id' => $arena->id]);

    MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    $input = ($this->catat)($this->match, $this->juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    $nama = collect((new JudgeInputReceived($input->fresh()))->broadcastOn())
        ->map(fn ($channel) => $channel->name)
        ->all();

    expect($nama)->toContain('presence-arena.'.$arena->id)
        ->and($nama)->toContain('public-live.'.$arena->id);
});
