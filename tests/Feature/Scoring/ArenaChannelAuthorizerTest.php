<?php

use App\Broadcasting\ArenaChannelAuthorizer;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->authorizer = app(ArenaChannelAuthorizer::class);
});

it('mengizinkan juri bergabung ke channel gelanggang lewat resource penilaian', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    expect(($this->authorizer)($juri, 1))->toBe(['id' => $juri->id, 'name' => $juri->name]);
});

it('mengizinkan wasit bergabung ke channel gelanggang lewat resource hukuman', function () {
    $wasit = User::factory()->create();
    $wasit->syncRoles(['wasit']);

    expect(($this->authorizer)($wasit, 1))->not->toBeFalse();
});

it('mengizinkan operator IT bergabung ke channel gelanggang lewat resource partai', function () {
    $operator = User::factory()->create();
    $operator->syncRoles(['operator-it']);

    expect(($this->authorizer)($operator, 1))->not->toBeFalse();
});

it('menolak pengguna tanpa peran pertandingan bergabung ke channel gelanggang', function () {
    $tanpaPeran = User::factory()->create();

    expect(($this->authorizer)($tanpaPeran, 1))->toBeFalse();
});

it('mengizinkan super admin tanpa peran khusus', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    expect(($this->authorizer)($admin, 1))->not->toBeFalse();
});
