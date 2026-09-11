<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Models\Contingent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/**
 * Nilai Jurus selalu dua desimal -- skalanya sendiri menyatakan langkah 0,01.
 * Sebelumnya 9.876543210987 diterima dengan pesan "Nilai tersimpan.", lalu
 * diam-diam berubah jadi 9.88 di basis data. Juri membaca konfirmasi berhasil
 * untuk angka yang bukan angka yang tersimpan.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $nomor = $this->tournament->jurusEvents()->aktif()->firstOrFail();

    $pendaftaran = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['jurus_event_id' => $nomor->id, 'weight_class_id' => null]);

    $this->performance = JurusPerformance::create([
        'jurus_event_id' => $nomor->id,
        'registration_id' => $pendaftaran->id,
        'tahap' => 'penyisihan',
    ]);

    $this->juri = User::factory()->create();
    $this->juri->syncRoles(['juri']);

    $this->kirim = fn (mixed $nilai) => $this->actingAs($this->juri)->postJson(
        route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]),
        ['value' => $nilai],
    );
});

it('menerima nilai dengan dua desimal', function (string $nilai) {
    ($this->kirim)($nilai)->assertOk();
})->with(['9', '9.5', '9.85', '10']);

it('menolak nilai yang bukan kelipatan seperseratus', function (string $nilai) {
    ($this->kirim)($nilai)
        ->assertStatus(422)
        ->assertJsonPath('errors.value.0', 'Nilai Jurus memakai kelipatan 0,01 — paling banyak dua angka di belakang koma.');
})->with(['9.876543210987', '9.999999', '9.005']);

it('tidak menyimpan apa pun saat nilainya ditolak', function () {
    ($this->kirim)('9.876543210987')->assertStatus(422);

    expect($this->performance->scores()->count())->toBe(0);
});

it('tetap menolak nilai di luar skala', function (string $nilai) {
    ($this->kirim)($nilai)->assertStatus(422);
})->with(['8.99', '10.01']);
