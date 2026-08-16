<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Contingent;
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

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kirim = fn (array $data) => $this->actingAs($this->admin)->post(
        "/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/atlet",
        [
            'name' => 'Bagas Pratama',
            'jenis_kelamin' => 'putra',
            'birth_date' => '2004-05-10',
            'weight_claim' => 58.0,
            ...$data,
        ],
    );
});

it('mendaftarkan kelas tanding sekaligus saat atlet ditambahkan', function () {
    ($this->kirim)(['daftar_tanding' => '1'])->assertSessionHasNoErrors();

    $atlet = $this->kontingen->athletes()->firstOrFail();
    $pendaftaran = $this->kontingen->registrations()->firstOrFail();

    // 58 kg dewasa putra jatuh tepat di kelas C (55–60 kg).
    expect($pendaftaran->weightClass->code)->toBe('C')
        ->and($pendaftaran->weightClass->golongan_usia)->toBe(GolonganUsia::Dewasa)
        ->and($pendaftaran->weightClass->jenis_kelamin)->toBe(JenisKelamin::Putra)
        ->and($pendaftaran->athletes->pluck('id')->all())->toBe([$atlet->id]);
});

it('tidak mendaftarkan apa pun bila tidak diminta', function () {
    ($this->kirim)(['daftar_tanding' => '0'])->assertSessionHasNoErrors();

    expect($this->kontingen->athletes()->count())->toBe(1)
        ->and($this->kontingen->registrations()->count())->toBe(0);
});

/*
 * Atlet yang identitasnya benar tidak boleh ikut hilang hanya karena bagian
 * pendaftaran nomornya gagal — official tinggal memilih kelasnya sendiri.
 */
it('tetap menyimpan atlet dan menjelaskan sebabnya saat tidak ada kelas yang cocok', function () {
    // Usia Dini 1 tidak mengenal kelas berat (Pasal 2), jadi tidak ada kelas
    // yang bisa dipilihkan sendiri.
    ($this->kirim)(['birth_date' => '2019-05-10', 'weight_claim' => 25, 'daftar_tanding' => '1'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('warning');

    expect($this->kontingen->athletes()->count())->toBe(1)
        ->and($this->kontingen->registrations()->count())->toBe(0);
});

it('mendaftarkan nomor jurus perorangan sekaligus', function () {
    $nomor = $this->tournament->jurusEvents()
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->get()
        ->firstWhere(fn ($n) => $n->jenis->jumlahPesilat() === 1);

    ($this->kirim)(['daftar_tanding' => '0', 'jurus_event_id' => $nomor->id])
        ->assertSessionHasNoErrors();

    expect($this->kontingen->registrations()->firstOrFail()->jurus_event_id)->toBe($nomor->id);
});

it('menolak nomor jurus yang golongan usianya tidak cocok, tanpa membuang atletnya', function () {
    $nomor = $this->tournament->jurusEvents()
        ->where('golongan_usia', GolonganUsia::UsiaDini1)
        ->get()
        ->firstWhere(fn ($n) => $n->jenis->jumlahPesilat() === 1);

    ($this->kirim)(['daftar_tanding' => '0', 'jurus_event_id' => $nomor->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('warning');

    expect($this->kontingen->athletes()->count())->toBe(1)
        ->and($this->kontingen->registrations()->count())->toBe(0);
});
