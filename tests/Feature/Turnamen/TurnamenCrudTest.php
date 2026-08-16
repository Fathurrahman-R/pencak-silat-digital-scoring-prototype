<?php

use App\Enums\StatusTurnamen;
use App\Models\Arena;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);
});

it('menutup seluruh modul kejuaraan dari pengguna tanpa hak akses', function (string $method, string $path) {
    $tanpaHak = User::factory()->create();

    $this->actingAs($tanpaHak)->call($method, $path)->assertForbidden();
})->with(function () {
    return [
        'daftar' => ['get', '/admin/turnamen'],
        'formulir baru' => ['get', '/admin/turnamen/create'],
        'simpan' => ['post', '/admin/turnamen'],
    ];
});

it('membuat kejuaraan beserta seluruh kelas dan nomor bawaan naskah', function () {
    $this->actingAs($this->admin)
        ->post('/admin/turnamen', [
            'name' => 'Kejuaraan Nasional 2026',
            'organizer' => 'PB IPSI',
            'venue' => 'GOR Bung Karno',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-05',
        ])
        ->assertRedirect();

    $tournament = Tournament::firstWhere('name', 'Kejuaraan Nasional 2026');

    expect($tournament)->not->toBeNull()
        ->and($tournament->status)->toBe(StatusTurnamen::Draf)
        ->and($tournament->slug)->toBe('kejuaraan-nasional-2026')
        ->and($tournament->ruleSetting)->not->toBeNull()
        ->and($tournament->weightClasses()->count())->toBe(174)
        ->and($tournament->jurusEvents()->count())->toBe(64);
});

it('memberi slug berbeda saat dua kejuaraan bernama sama', function () {
    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->admin)->post('/admin/turnamen', ['name' => 'Piala Wali Kota']);
    }

    expect(Tournament::pluck('slug')->all())
        ->toBe(['piala-wali-kota', 'piala-wali-kota-2']);
});

/*
 * Alamat halaman publik sudah beredar di poster dan grup pesan peserta
 * sebelum kejuaraan dimulai. Memperbaiki salah ketik pada namanya tidak boleh
 * mematikan tautan yang sudah dibagikan penonton.
 */
it('tidak mengubah slug saat nama kejuaraan diperbaiki', function () {
    $tournament = Tournament::factory()->create(['slug' => 'piala-wali-kota']);

    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$tournament->id}", ['name' => 'Piala Wali Kota Semarang']);

    expect($tournament->fresh())
        ->name->toBe('Piala Wali Kota Semarang')
        ->slug->toBe('piala-wali-kota');
});

it('menolak pendaftaran yang ditutup setelah hari pertama bertanding', function () {
    $this->actingAs($this->admin)
        ->post('/admin/turnamen', [
            'name' => 'Kejuaraan Uji',
            'starts_on' => '2026-09-01',
            'registration_closes_at' => '2026-09-03 10:00',
        ])
        ->assertSessionHasErrors('registration_closes_at');
});

it('menjalankan kejuaraan dari draf', function () {
    $tournament = Tournament::factory()->create(['status' => StatusTurnamen::Draf]);

    $this->actingAs($this->admin)
        ->patch("/admin/turnamen/{$tournament->id}/status", ['status' => 'berjalan'])
        ->assertSessionHasNoErrors();

    expect($tournament->fresh()->status)->toBe(StatusTurnamen::Berjalan);
});

/*
 * Menarik kejuaraan berjalan kembali ke draf membuka kunci setelan peraturan
 * sementara ada partai yang sudah dinilai memakai setelan lama. Aturannya
 * ditegakkan di server, bukan sekadar disembunyikan tombolnya.
 */
it('menolak menarik kejuaraan yang sudah berjalan kembali ke draf', function () {
    $tournament = Tournament::factory()->berjalan()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/turnamen/{$tournament->id}/status", ['status' => 'draf'])
        ->assertSessionHasErrors('status');

    expect($tournament->fresh()->status)->toBe(StatusTurnamen::Berjalan);
});

it('menampilkan daftar, formulir, dan panel detail kejuaraan', function () {
    $tournament = Tournament::factory()->create(['name' => 'Kejuaraan Terbuka']);

    $this->actingAs($this->admin)->get('/admin/turnamen')->assertOk()->assertSee('Kejuaraan Terbuka');
    $this->actingAs($this->admin)->get('/admin/turnamen/create')->assertOk();
    $this->actingAs($this->admin)->get("/admin/turnamen/{$tournament->id}/edit")->assertOk();
    $this->actingAs($this->admin)->get("/admin/turnamen/{$tournament->id}/panel")->assertOk()->assertSee('Kejuaraan Terbuka');
});

it('menambah gelanggang ke kejuaraan', function () {
    $tournament = Tournament::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$tournament->id}/gelanggang", [
            'name' => 'Gelanggang 1',
            'code' => 'G1',
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($tournament->arenas()->count())->toBe(1);
});

it('menolak kode gelanggang yang mengandung karakter di luar alamat aman', function () {
    $tournament = Tournament::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$tournament->id}/gelanggang", [
            'name' => 'Gelanggang Utama',
            'code' => 'G 1/utama',
        ])
        ->assertSessionHasErrors('code');
});

it('menolak kode gelanggang kembar di kejuaraan yang sama', function () {
    $tournament = Tournament::factory()->create();
    Arena::factory()->for($tournament)->create(['code' => 'G1']);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$tournament->id}/gelanggang", ['name' => 'Gelanggang 1', 'code' => 'G1'])
        ->assertSessionHasErrors('code');
});

/*
 * Tanpa penjagaan ini, mengganti satu angka di alamat berarti menyunting
 * gelanggang milik kejuaraan lain.
 */
it('menolak menyunting gelanggang lewat kejuaraan yang bukan pemiliknya', function () {
    $pemilik = Tournament::factory()->create();
    $lain = Tournament::factory()->create();
    $arena = Arena::factory()->for($pemilik)->create(['code' => 'G1']);

    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$lain->id}/gelanggang/{$arena->id}", ['name' => 'Dibajak', 'code' => 'G9'])
        ->assertNotFound();

    expect($arena->fresh()->name)->not->toBe('Dibajak');
});

it('menomori gelanggang baru di urutan paling belakang', function () {
    $tournament = Tournament::factory()->create();
    Arena::factory()->for($tournament)->create(['code' => 'G1', 'sort_order' => 5]);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$tournament->id}/gelanggang", ['name' => 'Gelanggang 2', 'code' => 'G2']);

    expect($tournament->arenas()->where('code', 'G2')->first()->sort_order)->toBe(6);
});
