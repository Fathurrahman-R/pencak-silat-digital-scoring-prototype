<?php

use App\Enums\StatusTurnamen;
use App\Http\Middleware\IngatTurnamenAktif;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);
});

it('menampilkan menu bagian kejuaraan sejak halaman pertama, tanpa menunggu satu kejuaraan dibuka', function () {
    $turnamen = Tournament::factory()->create(['name' => 'Kejuaraan Terbuka']);

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    $label = collect(app(NavigationBuilder::class)->build())->pluck('label');

    expect($label)->toContain($turnamen->name)
        ->and($label)->toContain('Peserta')
        ->and($label)->toContain('Keuangan');
});

it('memilih kejuaraan yang sedang berjalan lebih dulu sebagai bawaan', function () {
    Tournament::factory()->create(['status' => StatusTurnamen::Draf, 'starts_on' => now()->addYear()]);
    $berjalan = Tournament::factory()->create(['status' => StatusTurnamen::Berjalan]);

    $this->actingAs($this->admin);

    expect(app(NavigationBuilder::class)->turnamenAktif()?->id)->toBe($berjalan->id);
});

it('tetap mengutamakan kejuaraan yang terakhir dibuka daripada bawaannya', function () {
    $dibuka = Tournament::factory()->create(['status' => StatusTurnamen::Draf]);
    Tournament::factory()->create(['status' => StatusTurnamen::Berjalan]);

    session([IngatTurnamenAktif::KUNCI => $dibuka->id]);

    expect(app(NavigationBuilder::class)->turnamenAktif()?->id)->toBe($dibuka->id);
});

it('menyembunyikan bagian kejuaraan selama belum ada kejuaraan sama sekali', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    $label = collect(app(NavigationBuilder::class)->build())->pluck('label');

    expect($label)->not->toContain('Peserta')
        ->and($label)->toContain('Kejuaraan');
});
