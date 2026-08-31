<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusTurnamen;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;

/**
 * Halaman live dibuka penonton di lapangan, sering lewat ponsel dan jaringan
 * gelanggang. Merender seluruh katalog 174 kelas -- 172 di antaranya kosong
 * dan bertulis "Bagan belum tersusun" -- membuat halamannya berat sekaligus
 * mengubur dua kelas yang benar-benar dipertandingkan.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create([
        'starts_on' => '2026-09-01',
        'status' => StatusTurnamen::Berjalan,
    ]);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelasDipakai = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'A')->firstOrFail();

    Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasDipakai->id]);
});

it('hanya menampilkan kelas yang punya peserta', function () {
    $isi = $this->get("/live/turnamen/{$this->tournament->id}")->assertOk()->getContent();

    expect(substr_count($isi, 'Bagan belum tersusun'))->toBe(1);
});

it('menghitung kelas yang dipertandingkan, bukan seluruh katalog', function () {
    $seluruhKatalog = $this->tournament->weightClasses()->count();

    $this->get("/live/turnamen/{$this->tournament->id}")
        ->assertOk()
        ->assertSee('1 kelas')
        ->assertDontSee("{$seluruhKatalog} kelas");
});

it('tetap menampilkan kelas yang sudah punya bagan walau pendaftarannya dicabut', function () {
    Bracket::create(['weight_class_id' => $this->kelasDipakai->id, 'size' => 2]);
    Registration::query()->delete();

    $this->get("/live/turnamen/{$this->tournament->id}")
        ->assertOk()
        ->assertSee('1 kelas');
});
