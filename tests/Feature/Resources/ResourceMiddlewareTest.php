<?php

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Resources\ResourceManager;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app(ResourceManager::class)->createResource(
        ['key' => 'posts', 'label' => 'Artikel'],
        [ResourceAction::View, ResourceAction::Update, ResourceAction::Export],
    );

    Route::middleware('web')->group(function () {
        Route::get('/uji/satu', fn () => 'ok')->middleware('resource:posts.view');
        Route::get('/uji/atau', fn () => 'ok')->middleware('resource:posts.update|posts.export');
        Route::get('/uji/dan', fn () => 'ok')->middleware('resource:posts.view,posts.export');
    });
});

function userWith(array $permissions): User
{
    $role = Role::create(['name' => 'penguji_'.uniqid()]);
    $role->syncPermissions(Permission::whereIn('name', $permissions)->get());

    return User::factory()->create()->assignRole($role);
}

it('meloloskan pengguna yang punya key yang diminta', function () {
    $this->actingAs(userWith(['posts.view']))->get('/uji/satu')->assertOk();
});

it('menolak dengan 403 saat key tidak dimiliki', function () {
    $this->actingAs(userWith([]))->get('/uji/satu')->assertForbidden();
});

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/uji/satu')->assertRedirect(route('login'));
});

it('memperlakukan garis tegak sebagai ATAU', function () {
    $this->actingAs(userWith(['posts.export']))->get('/uji/atau')->assertOk();
    $this->actingAs(userWith(['posts.update']))->get('/uji/atau')->assertOk();
    $this->actingAs(userWith(['posts.view']))->get('/uji/atau')->assertForbidden();
});

it('memperlakukan koma sebagai DAN', function () {
    $this->actingAs(userWith(['posts.view', 'posts.export']))->get('/uji/dan')->assertOk();
    $this->actingAs(userWith(['posts.view']))->get('/uji/dan')->assertForbidden();
});

it('meloloskan super admin ke semua route', function () {
    /*
     * firstOrCreate, bukan create: peran super-admin lahir bersama seeder
     * basis yang kini berjalan sekali per proses uji (Tests\FeatureTestCase),
     * jadi membuatnya lagi melempar RoleAlreadyExists.
     */
    $role = Role::firstOrCreate(['name' => config('resources.super_admin_role'), 'guard_name' => 'web']);
    $user = User::factory()->create()->assignRole($role);

    $this->actingAs($user)->get('/uji/dan')->assertOk();
});
