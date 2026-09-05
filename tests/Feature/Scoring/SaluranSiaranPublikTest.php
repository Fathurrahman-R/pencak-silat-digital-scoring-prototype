<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\StatusBabak;
use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Events\Scoring\JudgeInputReceived;
use App\Events\Scoring\MatchStateChanged;
use App\Events\Scoring\PenaltyIssued;
use App\Events\Scoring\ScoreAwarded;
use App\Events\Scoring\TimerTicked;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeInput;
use App\Models\MatchRound;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;

/**
 * Inti penghematannya: kelima event siaran memakai satu daftar channel, dan
 * daftar itu menyusut jadi satu channel begitu kedua saklar dimatikan.
 *
 * Diuji dari model sungguhan, bukan dari SaluranArena langsung, karena yang
 * mudah hanyut bukan helper-nya melainkan sambungan tiap event ke helper itu
 * -- satu event yang lupa dialihkan tetap mendorong muatan ke Reverb tanpa
 * ada yang menyadarinya sampai hari-H.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create();

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $peserta = collect(['Merah', 'Biru'])->map(function () use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $reg;
    });

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $peserta[0]->id,
        'blue_registration_id' => $peserta[1]->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
        'arena_id' => $this->arena->id,
    ]);

    $this->babak = MatchRound::create([
        'match_id' => $this->match->id, 'round' => 1, 'duration_ms' => 120_000,
        'status' => StatusBabak::Berjalan, 'started_at' => now(),
    ]);

    /** @return array<int, string> */
    $this->namaSaluran = function (string $event): array {
        $peristiwa = match ($event) {
            TimerTicked::class => new TimerTicked($this->babak),
            MatchStateChanged::class => new MatchStateChanged($this->match),
            ScoreAwarded::class => new ScoreAwarded(ScoreEvent::create([
                'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
                'point_type' => JenisSerangan::Pukulan, 'value' => 1, 'server_ts' => now(),
            ])),
            PenaltyIssued::class => new PenaltyIssued(Penalty::create([
                'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
                'tier' => TingkatHukuman::Teguran, 'level' => 1, 'points' => -1,
                'violation_level' => 'sedang',
            ])),
            JudgeInputReceived::class => new JudgeInputReceived(JudgeInput::create([
                'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
                'point_type' => JenisSerangan::Pukulan,
                'judge_user_id' => User::factory()->create()->id,
                'server_ts' => now(),
            ])),
        };

        return collect($peristiwa->broadcastOn())->map(fn ($c) => $c->name)->all();
    };
});

$semuaEvent = [
    [TimerTicked::class],
    [ScoreAwarded::class],
    [PenaltyIssued::class],
    [MatchStateChanged::class],
    [JudgeInputReceived::class],
];

it('mencabut channel publik dari kelima event siaran saat kedua saklar mati', function (string $event) {
    config(['overlay.enabled' => false, 'live.enabled' => false]);

    expect(($this->namaSaluran)($event))->toBe(['presence-arena.'.$this->arena->id]);
})->with($semuaEvent);

it('tetap menyiarkan channel publik selama overlay menyala', function (string $event) {
    config(['overlay.enabled' => true, 'live.enabled' => false]);

    expect(($this->namaSaluran)($event))->toBe([
        'presence-arena.'.$this->arena->id,
        'public-live.'.$this->arena->id,
    ]);
})->with($semuaEvent);

it('tetap menyiarkan channel publik selama live score menyala', function (string $event) {
    config(['overlay.enabled' => false, 'live.enabled' => true]);

    expect(($this->namaSaluran)($event))->toBe([
        'presence-arena.'.$this->arena->id,
        'public-live.'.$this->arena->id,
    ]);
})->with($semuaEvent);

it('tetap menyiarkan ke panel meski partai belum punya gelanggang', function () {
    config(['overlay.enabled' => false, 'live.enabled' => false]);
    $this->match->update(['arena_id' => null]);
    $this->babak->refresh();

    expect(($this->namaSaluran)(MatchStateChanged::class))->toBe([]);
});
