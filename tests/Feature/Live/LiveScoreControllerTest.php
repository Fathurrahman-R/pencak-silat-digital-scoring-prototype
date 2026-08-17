<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $this->kelas = $kelas;

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => 'Budi']));
    $regBiru = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => 'Andi']));

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1, 'arena_id' => $this->arena->id,
    ]);
});

it('menyajikan halaman live score gelanggang tanpa login', function () {
    $this->get(route('live.gelanggang', $this->arena))
        ->assertOk()
        ->assertSee('overlayLive', false);
});

it('menyajikan state publik gelanggang berisi skor tanpa data juri', function () {
    $response = $this->getJson(route('live.gelanggang.state', $this->arena))->assertOk();

    $response->assertJsonPath('ada_partai', true)
        ->assertJsonMissingPath('officials')
        ->assertJsonMissingPath('riwayat');

    expect($response->json('red.nama'))->toBe('Budi');
});

it('menyajikan halaman turnamen dengan daftar gelanggang dan kelas', function () {
    $this->get(route('live.turnamen', $this->tournament))
        ->assertOk()
        ->assertSee('Gelanggang A')
        ->assertSee($this->kelas->name);
});

it('menyajikan bagan kelas untuk publik', function () {
    $this->get(route('live.turnamen.bagan', [$this->tournament, $this->kelas]))
        ->assertOk()
        ->assertSee('Budi')
        ->assertSee('Andi');
});

it('menyajikan halaman rekap medali publik', function () {
    $this->get(route('live.turnamen.medali', $this->tournament))
        ->assertOk()
        ->assertSee('Peringkat Umum');
});

it('menolak bagan kelas yang bukan milik turnamen', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-10-01']);

    $this->get(route('live.turnamen.bagan', [$turnamenLain, $this->kelas]))->assertNotFound();
});
