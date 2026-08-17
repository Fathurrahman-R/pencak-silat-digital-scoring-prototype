<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->sekretaris = User::factory()->create();
    $this->sekretaris->syncRoles(['sekretaris-pertandingan']);
});

it('menampilkan halaman rekap', function () {
    $this->actingAs($this->sekretaris)
        ->get(route('admin.turnamen.rekap.index', $this->tournament))
        ->assertOk()
        ->assertSee('Peringkat Umum Kontingen');
});

it('mengekspor rekap medali sebagai CSV', function () {
    $this->actingAs($this->sekretaris)
        ->get(route('admin.turnamen.rekap.ekspor.medali', $this->tournament))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('mengekspor daftar peserta sebagai CSV', function () {
    $this->actingAs($this->sekretaris)
        ->get(route('admin.turnamen.rekap.ekspor.peserta', $this->tournament))
        ->assertOk();
});

it('mengekspor jadwal sebagai CSV', function () {
    $this->actingAs($this->sekretaris)
        ->get(route('admin.turnamen.rekap.ekspor.jadwal', $this->tournament))
        ->assertOk();
});

it('user tanpa izin rekap ditolak', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->get(route('admin.turnamen.rekap.index', $this->tournament))
        ->assertForbidden();
});
