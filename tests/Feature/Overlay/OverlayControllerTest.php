<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => 'Budi Santoso']));

    $regBiru = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => 'Andi Wijaya']));

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id,
        'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
        'arena_id' => $this->arena->id,
    ]);
});

it('menyajikan state partai yang sedang berlangsung di gelanggang', function () {
    ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    $response = $this->getJson(route('overlay.state', $this->arena))->assertOk();

    $response->assertJsonPath('ada_partai', true)
        ->assertJsonPath('match.status', SilatMatch::STATUS_BERLANGSUNG)
        ->assertJsonPath('skor_total.merah', 1)
        ->assertJsonMissingPath('officials')
        ->assertJsonMissingPath('riwayat');

    expect($response->json('red.nama'))->toBeString()->not->toBe('');
});

it('kembali ke partai terakhir yang selesai bila tidak ada yang sedang berlangsung', function () {
    $this->match->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $this->match->red_registration_id,
        'win_reason' => 'angka',
    ]);

    $this->getJson(route('overlay.state', $this->arena))
        ->assertOk()
        ->assertJsonPath('ada_partai', true)
        ->assertJsonPath('match.win_reason', 'angka')
        ->assertJsonPath('match.winner_corner', 'red');
});

it('menandakan tidak ada partai relevan di gelanggang yang kosong', function () {
    $arenaKosong = Arena::factory()->for($this->tournament)->create();

    $this->getJson(route('overlay.state', $arenaKosong))
        ->assertOk()
        ->assertExactJson(['ada_partai' => false]);
});

it('menampilkan halaman scorebug dengan kanvas overlay transparan', function () {
    $this->get(route('overlay.scorebug', $this->arena))
        ->assertOk()
        ->assertSee('silat-overlay', false)
        ->assertSee('overlayLive', false);
});

it('menampilkan halaman lower third atlet untuk sudut merah', function () {
    $this->get(route('overlay.athlete', [$this->arena, 'red']))->assertOk();
});

it('menolak sudut yang tidak dikenal di halaman lower third', function () {
    $this->get(route('overlay.athlete', [$this->arena, 'hijau']))->assertNotFound();
});

it('menampilkan halaman rincian nilai dan hukuman', function () {
    $this->get(route('overlay.breakdown', $this->arena))->assertOk();
});

it('menampilkan halaman papan hasil', function () {
    $this->get(route('overlay.result', $this->arena))->assertOk();
});

it('menampilkan halaman bagan kosong tanpa parameter kelas', function () {
    $this->get(route('overlay.bracket', $this->tournament))->assertOk();
});

it('menampilkan bagan kelas yang diminta lewat query kelas', function () {
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->get(route('overlay.bracket', $this->tournament).'?kelas='.$kelas->id)
        ->assertOk()
        ->assertSee($kelas->name);
});
