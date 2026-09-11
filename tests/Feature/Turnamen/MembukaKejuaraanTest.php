<?php

use App\Http\Middleware\IngatTurnamenAktif;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Kejuaraan yang sedang dikerjakan hanya boleh berpindah kalau diminta.
 *
 * Sebelumnya setiap halaman ber-{tournament} ikut menggantinya, termasuk
 * "Ubah" dan panel intip di daftar kejuaraan. Menyunting alamat tempat
 * pertandingan satu kejuaraan mengganti seluruh isi sidebar ke kejuaraan itu,
 * tanpa satu kata pun yang memberi tahu -- dan panitia yang sedang mengurus
 * kejuaraan lain baru sadar setelah membuka menu yang salah.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->dikerjakan = Tournament::factory()->create(['name' => 'Yang Sedang Dikerjakan']);
    $this->lain = Tournament::factory()->create(['name' => 'Kejuaraan Lain']);

    $this->actingAs($this->admin);
    session([IngatTurnamenAktif::KUNCI => $this->dikerjakan->id]);
});

it('tidak berpindah saat tombol ubah di baris kejuaraan lain ditekan', function () {
    $this->get("/admin/turnamen/{$this->lain->id}/edit")->assertOk();

    expect(session(IngatTurnamenAktif::KUNCI))->toBe($this->dikerjakan->id);
});

it('tidak berpindah saat panel intip baris lain dibuka', function () {
    $this->get("/admin/turnamen/{$this->lain->id}/panel")->assertOk();

    expect(session(IngatTurnamenAktif::KUNCI))->toBe($this->dikerjakan->id);
});

it('tidak berpindah saat kejuaraan lain disunting', function () {
    $this->put("/admin/turnamen/{$this->lain->id}", [
        'name' => 'Kejuaraan Lain, Namanya Diubah',
        'status' => $this->lain->status->value,
    ])->assertRedirect();

    expect(session(IngatTurnamenAktif::KUNCI))->toBe($this->dikerjakan->id);
});

it('berpindah lewat tombol buka, dan mendarat di beranda', function () {
    $this->post("/admin/turnamen/{$this->lain->id}/buka")
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    expect(session(IngatTurnamenAktif::KUNCI))->toBe($this->lain->id);
});

/*
 * Halaman yang benar-benar ADA DI DALAM kejuaraan tetap memindahkannya:
 * tanpa itu, tautan langsung ke jadwal kejuaraan lain akan menampilkan
 * jadwal yang satu sementara sidebar menyebut yang lain.
 */
it('tetap berpindah saat halaman di dalam kejuaraan lain dibuka', function () {
    // Gelanggang, bukan jadwal: jadwal menuntut setelan peraturan, dan yang
    // diuji di sini perpindahan konteksnya, bukan kelengkapan datanya.
    $this->get("/admin/turnamen/{$this->lain->id}/gelanggang")->assertOk();

    expect(session(IngatTurnamenAktif::KUNCI))->toBe($this->lain->id);
});

it('menandai baris yang sedang dibuka dan tidak menawarkan tombol buka di sana', function () {
    $halaman = $this->get('/admin/turnamen')->assertOk();

    $halaman->assertSee('Sedang dibuka')
        ->assertSee(route('admin.turnamen.buka', $this->lain), escape: false)
        ->assertDontSee(route('admin.turnamen.buka', $this->dikerjakan), escape: false);
});
