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
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 1']);

    $this->buatPartai = function (string $kodeKelas = 'C'): SilatMatch {
        $kelas = $this->tournament->weightClasses()
            ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', $kodeKelas)->firstOrFail();

        $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

        $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $regMerah->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

        $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $regBiru->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
            'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
        ]);
    };
});

it('menampilkan gelanggang beserta partai yang belum dijadwalkan', function () {
    ($this->buatPartai)();

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.jadwal.index', $this->tournament))
        ->assertOk()
        ->assertSee('Gelanggang 1')
        ->assertSee('Belum dijadwalkan');
});

it('menjadwalkan partai lewat form', function () {
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
            'arena_id' => $this->arena->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($partai->fresh()->arena_id)->toBe($this->arena->id);
});

it('menolak menjadwalkan ke gelanggang kejuaraan lain', function () {
    $partai = ($this->buatPartai)();
    $arenaLain = Arena::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
            'arena_id' => $arenaLain->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('arena_id');

    expect($partai->fresh()->arena_id)->toBeNull();
});

it('melepas jadwal partai lewat form', function () {
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
        'arena_id' => $this->arena->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partai]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($partai->fresh()->arena_id)->toBeNull();
});

it('menolak partai yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $kelasLain = $turnamenLain->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracketLain = Bracket::create(['weight_class_id' => $kelasLain->id, 'size' => 2]);
    $partaiLain = SilatMatch::create([
        'bracket_id' => $bracketLain->id, 'round' => 1, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partaiLain]))
        ->assertNotFound();
});
