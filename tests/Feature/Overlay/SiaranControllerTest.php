<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Halaman ini ada supaya operator vMix tidak perlu menebak `{arena}` di
 * alamat overlay. Yang diuji karena itu bukan tampilannya, melainkan bahwa
 * alamat yang tercetak benar-benar alamat yang berlaku — kalau rute overlay
 * berubah bentuk, test ini yang gagal lebih dulu, bukan operator di hari-H.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::create([
        'tournament_id' => $this->tournament->id,
        'name' => 'Gelanggang A',
        'code' => 'A',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->operator = User::factory()->create();
    $this->operator->syncRoles(['operator-it']);
});

it('mencetak alamat kelima halaman overlay untuk tiap gelanggang', function () {
    $this->actingAs($this->operator)
        ->get(route('admin.turnamen.siaran.index', $this->tournament))
        ->assertOk()
        ->assertSee('Gelanggang A')
        ->assertSee(route('overlay.scorebug', $this->arena), false)
        ->assertSee(route('overlay.athlete', [$this->arena, 'red']), false)
        ->assertSee(route('overlay.athlete', [$this->arena, 'blue']), false)
        ->assertSee(route('overlay.breakdown', $this->arena), false)
        ->assertSee(route('overlay.result', $this->arena), false);
});

it('mencetak alamat bagan hanya untuk kelas yang bagannya sudah tersusun', function () {
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $tanpaBagan = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'D')->firstOrFail();

    Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->actingAs($this->operator)
        ->get(route('admin.turnamen.siaran.index', $this->tournament))
        ->assertOk()
        ->assertSee(route('overlay.bracket', $this->tournament).'?kelas='.$kelas->id, false)
        ->assertDontSee(route('overlay.bracket', $this->tournament).'?kelas='.$tanpaBagan->id, false);
});

it('menuntun pengguna menambah gelanggang bila belum ada', function () {
    $this->arena->delete();

    $this->actingAs($this->operator)
        ->get(route('admin.turnamen.siaran.index', $this->tournament))
        ->assertOk()
        ->assertSee('Belum ada gelanggang');
});

it('menolak peran yang tidak memasang overlay', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.siaran.index', $this->tournament))
        ->assertForbidden();
});

it('mengizinkan ketua pertandingan melihat daftar alamat', function () {
    $ketua = User::factory()->create();
    $ketua->syncRoles(['ketua-pertandingan']);

    $this->actingAs($ketua)
        ->get(route('admin.turnamen.siaran.index', $this->tournament))
        ->assertOk();
});
