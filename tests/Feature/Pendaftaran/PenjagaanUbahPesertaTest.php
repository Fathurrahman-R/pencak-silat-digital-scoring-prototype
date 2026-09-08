<?php

use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Tiga penjagaan yang menahan daftar peserta tetap cocok dengan tagihannya,
 * dan menahan satu kejuaraan tidak menyentuh kontingen kejuaraan lain.
 *
 * Ketiganya ditemukan pada uji lapangan 8 September 2026: yang pertama lewat
 * tombol Batalkan yang tetap tampil sesudah tagihan lunas, yang kedua lewat
 * menghapus atlet yang pendaftarannya sudah terverifikasi, dan yang ketiga
 * lewat mengetik id kontingen kejuaraan lain di alamat.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create();
    $this->contingent = Contingent::factory()->for($this->tournament)->create();
});

/**
 * Menambah peserta pada tagihan terkunci sudah lama ditolak; menghapusnya
 * dulu tidak. Tagihan tetap menyebut jumlah nomor yang lama, dan selisihnya
 * baru ketahuan saat rekap keuangan tidak cocok dengan daftar peserta.
 */
it('menolak menghapus pendaftaran saat tagihan sudah terkunci', function () {
    $registration = Registration::factory()->for($this->contingent)->create();

    Invoice::create([
        'contingent_id' => $this->contingent->id,
        'number' => 'INV-UJI-001',
        'status' => 'menunggu_pembayaran',
        'total_amount' => 150000,
        'locked_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->contingent->id}/pendaftaran/{$registration->id}")
        ->assertSessionHasErrors('pendaftaran');

    expect(Registration::find($registration->id))->not->toBeNull();
});

it('membolehkan menghapus pendaftaran selama tagihan masih draf', function () {
    $registration = Registration::factory()->for($this->contingent)->create();

    Invoice::create([
        'contingent_id' => $this->contingent->id,
        'number' => 'INV-UJI-002',
        'status' => 'draf',
        'total_amount' => 150000,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->contingent->id}/pendaftaran/{$registration->id}")
        ->assertSessionHasNoErrors();

    expect(Registration::find($registration->id))->toBeNull();
});

/**
 * Tabel penghubungnya ikut terbuang, pendaftarannya tidak. Yang tertinggal
 * adalah pendaftaran hidup tanpa satu pun peserta -- masih terhitung "peserta
 * sah" di halaman Bagan, dan masuk susunan bagan sebagai tempat tanpa nama.
 */
it('menolak menghapus atlet yang masih memegang pendaftaran', function () {
    $athlete = Athlete::factory()->for($this->contingent)->create();
    $registration = Registration::factory()->for($this->contingent)->create();
    $registration->athletes()->attach($athlete);

    $this->actingAs($this->admin)
        ->delete("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->contingent->id}/atlet/{$athlete->id}")
        ->assertSessionHasErrors('atlet');

    expect(Athlete::find($athlete->id))->not->toBeNull()
        ->and(Registration::find($registration->id)->athletes()->count())->toBe(1);
});

it('membolehkan menghapus atlet yang belum terdaftar di nomor mana pun', function () {
    $athlete = Athlete::factory()->for($this->contingent)->create();

    $this->actingAs($this->admin)
        ->delete("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->contingent->id}/atlet/{$athlete->id}")
        ->assertSessionHasNoErrors();

    expect(Athlete::find($athlete->id))->toBeNull();
});

/*
 * Kontingen milik kejuaraan lain tidak boleh terbuka di bawah alamat kejuaraan
 * ini. Sebelum `scopeBindings()`, halamannya menjawab 200 dan menulis nama
 * kontingen kejuaraan A di bawah judul kejuaraan B -- lengkap dengan daftar
 * atlet, tombol tambah, dan tagihannya.
 */
it('menolak kontingen milik kejuaraan lain di alamat kejuaraan ini', function (string $jalur) {
    $lain = Contingent::factory()->for(Tournament::factory()->create())->create();

    $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$lain->id}{$jalur}")
        ->assertNotFound();
})->with([
    'atlet' => '/atlet',
    'pendaftaran' => '/pendaftaran',
    'tagihan' => '/tagihan',
    'edit' => '/edit',
]);
