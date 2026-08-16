<?php

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\ResourcePermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Resources\ResourceGate;
use App\Support\Resources\ResourceManager;
use App\Support\Resources\ResourceMap;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->superAdmin = User::factory()->create(['email_verified_at' => now()])
        ->assignRole(config('resources.super_admin_role'));
});

it('membuat resource beserta permission dan pemetaannya lewat form', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.resources.store'), [
            'key' => 'Laporan Bulanan',   // sengaja pakai spasi dan huruf besar
            'label' => 'Laporan Bulanan',
            'group' => 'Keuangan',
            'actions' => [ResourceAction::View->value, ResourceAction::Export->value],
        ])
        ->assertRedirect();

    $resource = Resource::where('key', 'laporan_bulanan')->firstOrFail();

    expect($resource->mappings)->toHaveCount(2);
    expect(Permission::where('name', 'laporan_bulanan.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'laporan_bulanan.export')->exists())->toBeTrue();
});

it('menolak nama aksi di luar enum', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.resources.store'), [
            'key' => 'laporan',
            'label' => 'Laporan',
            'actions' => ['hapus_semua'],
        ])
        ->assertSessionHasErrors('actions.0');

    expect(Resource::where('key', 'laporan')->exists())->toBeFalse();
});

it('mengarahkan key ke permission lain tanpa mengubah kode', function () {
    $editor = Role::create(['name' => 'editor']);
    $user = User::factory()->create(['email_verified_at' => now()])->assignRole($editor);

    $gate = app(ResourceGate::class);
    expect($gate->allows('turnamen.export', $user))->toBeFalse();

    // Permission baru, dibuat lewat modul Permission.
    $this->actingAs($this->superAdmin)
        ->post(route('admin.permissions.store'), ['name' => 'akses-laporan'])
        ->assertRedirect();

    $permission = Permission::where('name', 'akses-laporan')->firstOrFail();
    $editor->givePermissionTo($permission);

    // Key turnamen.export sekarang menunjuk permission itu.
    $mapping = ResourcePermission::whereHas('resource', fn ($q) => $q->where('key', 'turnamen'))
        ->where('action', ResourceAction::Export->value)
        ->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->put(route('admin.mappings.update', $mapping), ['permission_id' => $permission->id])
        ->assertRedirect();

    expect(app(ResourceGate::class)->allows('turnamen.export', $user->fresh()))->toBeTrue();
});

it('menutup akses saat pemetaan dilepas', function () {
    $editor = Role::create(['name' => 'editor']);
    $editor->givePermissionTo(Permission::where('name', 'turnamen.view')->firstOrFail());
    $user = User::factory()->create(['email_verified_at' => now()])->assignRole($editor);

    expect(app(ResourceGate::class)->allows('turnamen.view', $user))->toBeTrue();

    $mapping = ResourcePermission::whereHas('resource', fn ($q) => $q->where('key', 'turnamen'))
        ->where('action', ResourceAction::View->value)
        ->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.mappings.destroy', $mapping))
        ->assertRedirect();

    expect(app(ResourceGate::class)->allows('turnamen.view', $user->fresh()))->toBeFalse();
    expect($mapping->fresh()->permission_id)->toBeNull();
});

it('memetakan ulang seluruh key kosong dengan satu aksi', function () {
    ResourcePermission::query()->update(['permission_id' => null]);
    app(ResourceMap::class)->flush();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.mappings.auto'))
        ->assertRedirect();

    expect(ResourcePermission::whereNull('permission_id')->count())->toBe(0);
});

it('melindungi resource inti dari penghapusan', function () {
    $resource = Resource::where('key', 'roles')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.resources.destroy', $resource))
        ->assertSessionHas('error');

    expect(Resource::where('key', 'roles')->exists())->toBeTrue();
});

/*
 * Resource dibuat di dalam test, bukan diambil dari seeder. Hampir seluruh
 * resource domain ditandai terkunci justru supaya tidak bisa dihapus, dan
 * menggantungkan test ini pada salah satunya berarti ia ikut gagal setiap kali
 * sebuah resource dinaikkan menjadi terkunci.
 */
it('menghapus resource tanpa menghapus permission-nya', function () {
    $resource = app(ResourceManager::class)->createResource(
        ['key' => 'coba-hapus', 'label' => 'Coba Hapus'],
        [ResourceAction::View],
    );

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.resources.destroy', $resource))
        ->assertRedirect();

    expect(Resource::where('key', 'coba-hapus')->exists())->toBeFalse()
        ->and(Permission::where('name', 'coba-hapus.view')->exists())->toBeTrue();
});

it('menyembunyikan menu yang key-nya tidak dimiliki', function () {
    $editor = Role::create(['name' => 'editor']);
    $editor->givePermissionTo(Permission::where('name', 'turnamen.view')->firstOrFail());
    $user = User::factory()->create(['email_verified_at' => now()])->assignRole($editor);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Kejuaraan');
    $response->assertDontSee('Manajemen Akses');
});
