<?php

use App\Models\Arena;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Pengendali gelanggang boleh MELIHAT panel kendali gelanggang lain -- ia perlu
 * tahu matras sebelah sedang di partai mana -- tapi tidak boleh menekan apa pun
 * di dalamnya.
 *
 * Server sudah lama menolak aksinya dengan 403. Yang tidak dilakukan adalah
 * menyembunyikan tombolnya: panel gelanggang sebelah tampil lengkap dengan
 * Tayangkan, Mulai babak, dan Kosongkan gelanggang, dan setiap satu dijawab
 * penolakan. Terlihat pada uji lapangan 8 September 2026. Yang menekan
 * "Kosongkan gelanggang" di gelanggang sebelah tidak selalu membaca pesan
 * galatnya, dan sebagian mengira gelanggangnya benar-benar kosong.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create();

    $this->milik = Arena::factory()->for($this->tournament)->create(['code' => 'A', 'name' => 'Gelanggang A']);
    $this->sebelah = Arena::factory()->for($this->tournament)->create(['code' => 'B', 'name' => 'Gelanggang B']);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->milik->pengendali()->attach($this->pengendali);
});

it('menampilkan kendali penuh di gelanggang yang dipegangnya', function () {
    $this->actingAs($this->pengendali)
        ->get("/admin/turnamen/{$this->tournament->id}/gelanggang/{$this->milik->id}/panel/kendali")
        ->assertOk()
        ->assertSee('Kosongkan gelanggang')
        ->assertDontSee('hanya untuk dilihat');
});

it('menyembunyikan seluruh kendali di gelanggang yang bukan dipegangnya', function () {
    $this->actingAs($this->pengendali)
        ->get("/admin/turnamen/{$this->tournament->id}/gelanggang/{$this->sebelah->id}/panel/kendali")
        ->assertOk()
        ->assertSee('hanya untuk dilihat')
        ->assertDontSee('Kosongkan gelanggang')
        ->assertDontSee('Tayangkan');
});

/** Penjagaan sesungguhnya tetap di server; tombol yang hilang cuma lapis pertama. */
it('tetap menolak aksi di gelanggang yang bukan dipegangnya', function () {
    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/gelanggang/{$this->sebelah->id}/panel/partai-aktif", [
            'match_id' => null,
        ])
        ->assertForbidden();
});

/**
 * Peran lain yang memegang izin kendali TIDAK dibatasi per gelanggang --
 * merekalah yang menambal saat pengendali berhalangan.
 */
it('membiarkan panitia mengendalikan gelanggang mana pun', function () {
    $panitia = User::factory()->create();
    $panitia->syncRoles([config('resources.super_admin_role')]);

    $this->actingAs($panitia)
        ->get("/admin/turnamen/{$this->tournament->id}/gelanggang/{$this->sebelah->id}/panel/kendali")
        ->assertOk()
        ->assertSee('Kosongkan gelanggang')
        ->assertDontSee('hanya untuk dilihat');
});
