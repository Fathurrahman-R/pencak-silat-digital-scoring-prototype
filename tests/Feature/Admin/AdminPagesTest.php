<?php

use App\Models\Resource;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->superAdmin = User::factory()->create(['email_verified_at' => now()])
        ->assignRole(config('resources.super_admin_role'));
});

it('membuka semua halaman panel admin', function (string $route) {
    $this->actingAs($this->superAdmin)->get(route($route))->assertOk();
})->with([
    'dashboard',
    'profile.edit',
    'admin.users.index',
    'admin.users.create',
    'admin.roles.index',
    'admin.roles.create',
    'admin.permissions.index',
    'admin.permissions.create',
    'admin.resources.index',
    'admin.resources.create',
    'admin.mappings.index',
    'admin.turnamen.index',
    'admin.turnamen.create',
]);

it('membuka halaman detail dan ubah', function () {
    $resource = Resource::where('key', 'turnamen')->firstOrFail();
    $tournament = Tournament::factory()->create();
    $permission = $resource->mappings->first()->permission;
    $role = Role::where('name', 'admin')->firstOrFail();

    $this->actingAs($this->superAdmin);

    $this->get(route('admin.resources.show', $resource))->assertOk();
    $this->get(route('admin.resources.edit', $resource))->assertOk();
    $this->get(route('admin.permissions.edit', $permission))->assertOk();
    $this->get(route('admin.roles.edit', $role))->assertOk();
    $this->get(route('admin.users.edit', $this->superAdmin))->assertOk();
    $this->get(route('admin.turnamen.edit', $tournament))->assertOk();
});

it('membuka halaman auth untuk tamu', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['login', 'register', 'password.request']);

it('menolak tamu masuk ke panel', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

it('mengekspor CSV', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
