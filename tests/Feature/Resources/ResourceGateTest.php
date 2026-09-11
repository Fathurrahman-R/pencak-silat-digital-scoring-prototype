<?php

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Support\Resources\ResourceGate;
use App\Support\Resources\ResourceManager;

function gate(): ResourceGate
{
    return app(ResourceGate::class);
}

function manager(): ResourceManager
{
    return app(ResourceManager::class);
}

function userWithPermissions(array $permissionNames): User
{
    $role = Role::create(['name' => 'penguji_'.uniqid()]);
    $role->syncPermissions(Permission::whereIn('name', $permissionNames)->get());

    return User::factory()->create()->assignRole($role);
}

beforeEach(function () {
    $this->resource = manager()->createResource(
        ['key' => 'posts', 'label' => 'Artikel', 'group' => 'Konten'],
        [ResourceAction::View, ResourceAction::Update, ResourceAction::Delete],
    );
});

it('mengizinkan pengguna yang punya permission hasil pemetaan', function () {
    $user = userWithPermissions(['posts.view']);

    expect(gate()->allows('posts.view', $user))->toBeTrue();
    expect(gate()->allows('posts.update', $user))->toBeFalse();
});

it('menolak tamu yang belum login', function () {
    expect(gate()->allows('posts.view', null))->toBeFalse();
});

it('meloloskan super admin tanpa permission apa pun', function () {
    /*
     * firstOrCreate, bukan create: peran super-admin lahir bersama seeder
     * basis yang kini berjalan sekali per proses uji (Tests\FeatureTestCase),
     * jadi membuatnya lagi melempar RoleAlreadyExists.
     */
    $role = Role::firstOrCreate(['name' => config('resources.super_admin_role'), 'guard_name' => 'web']);
    $user = User::factory()->create()->assignRole($role);

    expect(gate()->allows('posts.view', $user))->toBeTrue();
    expect(gate()->allows('apa_saja.manage', $user))->toBeTrue();
});

it('menolak key yang tidak terdaftar', function () {
    $user = userWithPermissions(['posts.view']);

    expect(gate()->allows('tidak_ada.view', $user))->toBeFalse();
});

it('menolak key yang terdaftar tapi belum dipetakan ke permission', function () {
    $user = userWithPermissions(['posts.view']);

    // Melepas pemetaan harus langsung menutup aksesnya, bukan menunggu cache
    // kedaluwarsa.
    $mapping = $this->resource->mappings()->where('action', ResourceAction::View->value)->first();
    manager()->remap($mapping, null);

    expect(gate()->allows('posts.view', $user))->toBeFalse();
});

it('mengikuti pemetaan baru saat key diarahkan ke permission lain', function () {
    $user = userWithPermissions(['akses-artikel']);

    expect(gate()->allows('posts.view', $user))->toBeFalse();

    $permission = manager()->createPermission(['name' => 'akses-artikel']);
    $user->roles->first()->givePermissionTo($permission);

    $mapping = $this->resource->mappings()->where('action', ResourceAction::View->value)->first();
    manager()->remap($mapping, $permission);

    expect(gate()->allows('posts.view', $user->fresh()))->toBeTrue();
});

it('menghitung any sebagai ATAU dan all sebagai DAN', function () {
    $user = userWithPermissions(['posts.view']);

    expect(gate()->any(['posts.view', 'posts.delete'], $user))->toBeTrue();
    expect(gate()->any(['posts.update', 'posts.delete'], $user))->toBeFalse();
    expect(gate()->all(['posts.view', 'posts.delete'], $user))->toBeFalse();
    expect(gate()->all(['posts.view'], $user))->toBeTrue();
    expect(gate()->all([], $user))->toBeFalse();
});

it('menutup akses saat permission yang dipetakan dihapus, tanpa menghilangkan key', function () {
    $user = userWithPermissions(['posts.view']);
    expect(gate()->allows('posts.view', $user))->toBeTrue();

    manager()->deletePermission(Permission::where('name', 'posts.view')->first());

    expect(gate()->allows('posts.view', $user->fresh()))->toBeFalse();

    // Key-nya tetap terdaftar, hanya kehilangan permission-nya.
    $mapping = $this->resource->mappings()->where('action', ResourceAction::View->value)->first();
    expect($mapping)->not->toBeNull();
    expect($mapping->isMapped())->toBeFalse();
});

it('tidak terpengaruh perubahan nama permission karena pemetaan memakai id', function () {
    $user = userWithPermissions(['posts.view']);

    manager()->updatePermission(
        Permission::where('name', 'posts.view')->first(),
        ['name' => 'lihat-artikel'],
    );

    expect(gate()->allows('posts.view', $user->fresh()))->toBeTrue();
    expect(Resource::where('key', 'posts')->first()->mappings()->count())->toBe(3);
});
