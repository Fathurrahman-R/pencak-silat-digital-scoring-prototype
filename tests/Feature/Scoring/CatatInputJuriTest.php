<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\StatusBabak;
use App\Enums\Sudut;
use App\Events\Scoring\JudgeInputReceived;
use App\Events\Scoring\ScoreAwarded;
use App\Models\Bracket;
use App\Models\Contingent;
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

    expect($input->ditolak())->toBeTrue()
        ->and($input->rejected_reason)->toContain('tidak berjalan');

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
