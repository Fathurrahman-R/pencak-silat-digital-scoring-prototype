<?php

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/**
 * Menu samping selalu menulis "Beranda". Halaman yang dibukanya harus
 * memakai kata yang sama, apa pun perannya -- panitia yang menekan "Beranda"
 * lalu mendarat di halaman berjudul "Dashboard" akan mengira ia salah klik.
 *
 * Yang membedakan panitia dan aparat tetap ada, tapi di keterangannya, bukan
 * di judulnya.
 */
beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    Tournament::factory()->create(['starts_on' => '2026-09-01']);

    $this->masuk = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $this->actingAs($user)->get('/dashboard')->assertOk();
    };
});

it('memakai judul Indonesia yang sama dengan menunya', function (string $peran) {
    ($this->masuk)($peran)
        ->assertSee('Beranda')
        ->assertDontSee('Dashboard');
})->with(['sekretaris-pertandingan', 'juri', 'operator-it', 'bendahara']);

it('tetap membedakan panitia dan aparat lewat keterangannya', function () {
    ($this->masuk)('juri')->assertSee('Partai tempat Anda ditugaskan hari ini.');
});
