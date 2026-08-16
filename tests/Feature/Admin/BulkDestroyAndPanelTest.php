<?php

use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Resources\ResourceManager;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->superAdmin = User::factory()->create(['email_verified_at' => now()])
        ->assignRole(config('resources.super_admin_role'));
});

/** Pengguna tanpa role atau permission apa pun — dipakai untuk uji 403. */
function bareUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('membuka panel detail pengguna untuk yang punya izin lihat', function () {
    $target = User::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.panel', $target))
        ->assertOk()
        ->assertSee($target->email);
});

it('menolak panel detail pengguna tanpa izin', function () {
    $target = User::factory()->create();

    $this->actingAs(bareUser())
        ->get(route('admin.users.panel', $target))
        ->assertForbidden();
});

it('menghapus beberapa pengguna sekaligus lewat bulk destroy', function () {
    $victims = User::factory()->count(3)->create();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.users.bulk-destroy'), ['ids' => $victims->pluck('id')->all()])
        ->assertRedirect(route('admin.users.index'));

    foreach ($victims as $victim) {
        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }
});

it('melewati diri sendiri saat bulk destroy pengguna menyertakan akun sendiri', function () {
    $victim = User::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.users.bulk-destroy'), ['ids' => [$victim->id, $this->superAdmin->id]]);

    $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
});

it('menolak bulk destroy pengguna tanpa izin hapus', function () {
    $target = User::factory()->create();

    $this->actingAs(bareUser())
        ->post(route('admin.users.bulk-destroy'), ['ids' => [$target->id]])
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('melindungi role terkunci dari bulk destroy', function () {
    $superAdminRole = Role::where('name', config('resources.super_admin_role'))->firstOrFail();
    $extra = Role::create(['name' => 'penguji_bebas', 'guard_name' => 'web']);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.bulk-destroy'), ['ids' => [$superAdminRole->id, $extra->id]])
        ->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id]);
    $this->assertDatabaseMissing('roles', ['id' => $extra->id]);
});

it('menghapus beberapa kejuaraan sekaligus lewat bulk destroy', function () {
    $tournaments = Tournament::factory()->count(2)->create();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.turnamen.bulk-destroy'), ['ids' => $tournaments->pluck('id')->all()])
        ->assertRedirect(route('admin.turnamen.index'));

    foreach ($tournaments as $tournament) {
        $this->assertSoftDeleted('tournaments', ['id' => $tournament->id]);
    }
});

it('membuka panel detail kejuaraan', function () {
    $tournament = Tournament::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.turnamen.panel', $tournament))
        ->assertOk()
        ->assertSee($tournament->name);
});

it('membuka panel detail role', function () {
    $role = Role::where('name', 'admin')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.roles.panel', $role))
        ->assertOk()
        ->assertSee($role->name);
});

it('melindungi permission terkunci dari bulk destroy', function () {
    $locked = Permission::where('is_locked', true)->firstOrFail();
    $loose = Permission::create(['name' => 'longgar.lihat', 'guard_name' => 'web', 'is_locked' => false]);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.permissions.bulk-destroy'), ['ids' => [$locked->id, $loose->id]])
        ->assertRedirect(route('admin.permissions.index'));

    $this->assertDatabaseHas('permissions', ['id' => $locked->id]);
    $this->assertDatabaseMissing('permissions', ['id' => $loose->id]);
});

it('melindungi resource terkunci dari bulk destroy', function () {
    $locked = Resource::where('is_locked', true)->firstOrFail();
    $loose = app(ResourceManager::class)->createResource([
        'key' => 'longgar', 'label' => 'Longgar',
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.resources.bulk-destroy'), ['ids' => [$locked->id, $loose->id]])
        ->assertRedirect(route('admin.resources.index'));

    $this->assertDatabaseHas('resources', ['id' => $locked->id]);
    $this->assertDatabaseMissing('resources', ['id' => $loose->id]);
});
