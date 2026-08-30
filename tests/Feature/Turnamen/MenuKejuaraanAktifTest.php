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

/** Nama seksi yang terbentuk untuk pengguna yang sedang login. */
function seksi(): Illuminate\Support\Collection
{
    return collect(app(NavigationBuilder::class)->build())
        ->where('tipe', 'seksi')
        ->pluck('label');
}

/** Label seluruh item, lepas dari seksinya. */
function itemMenu(): Illuminate\Support\Collection
{
    return collect(app(NavigationBuilder::class)->semuaItem())->pluck('label');
}

it('menampilkan menu di dalam kejuaraan sejak halaman pertama, tanpa menunggu satu kejuaraan dibuka', function () {
    Tournament::factory()->create(['name' => 'Kejuaraan Terbuka']);

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    expect(seksi())->toContain('Peserta')
        ->and(seksi())->toContain('Keuangan')
        ->and(itemMenu())->toContain('Timbang badan');
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

/*
 * Seksi yang seluruh itemnya butuh kejuaraan aktif ikut hilang -- judul seksi
 * tanpa isi hanya membuat orang mengira ada yang gagal dimuat.
 */
it('menyembunyikan seksi yang seluruh itemnya butuh kejuaraan, selama belum ada kejuaraan sama sekali', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    expect(seksi())->not->toContain('Peserta')
        ->and(seksi())->not->toContain('Keuangan')
        // Seksi Kejuaraan bertahan: "Daftar kejuaraan" tidak butuh kejuaraan
        // aktif, dan justru dari sanalah kejuaraan pertama dibuat.
        ->and(seksi())->toContain('Kejuaraan')
        ->and(itemMenu())->toContain('Daftar kejuaraan')
        ->and(itemMenu())->not->toContain('Gelanggang');
});

/*
 * Di lebar rail hanya ikon yang tersisa. Satu item tanpa ikon jadi baris
 * kosong yang tidak bisa dikenali sama sekali -- dan itu tidak akan ketahuan
 * sampai seseorang menciutkan menunya.
 */
it('memberi ikon pada setiap item menu', function () {
    Tournament::factory()->create();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    $tanpaIkon = collect(app(NavigationBuilder::class)->semuaItem())
        ->whereNull('icon')
        ->pluck('label')
        ->all();

    expect($tanpaIkon)->toBe([], 'Item menu tanpa ikon: '.implode(', ', $tanpaIkon));
});

/*
 * Susunannya datar. Kalau suatu saat seseorang menambahkan `children` ke
 * config, sidebarnya akan diam-diam mengabaikannya -- itemnya tidak pernah
 * tampil, dan tidak ada galat yang menyebutkannya.
 */
it('tidak menghasilkan menu bertingkat', function () {
    Tournament::factory()->create();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();

    foreach (app(NavigationBuilder::class)->semuaItem() as $item) {
        expect($item)->not->toHaveKey('children');
    }
});
