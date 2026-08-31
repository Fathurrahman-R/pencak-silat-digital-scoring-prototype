<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusTurnamen;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;

/**
 * Katalog kelas dari naskah aturan 2025 berisi 174 kelas, tapi satu
 * kejuaraan hanya mempertandingkan sebagian kecilnya. Menghitung seluruh
 * katalog membuat halaman masuk mengumumkan "174 kelas dipertandingkan"
 * untuk kejuaraan yang sebenarnya hanya menjalankan dua kelas.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create([
        'starts_on' => '2026-09-01',
        'status' => StatusTurnamen::Berjalan,
    ]);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
});

it('menghitung hanya kelas yang benar-benar punya peserta', function () {
    foreach (['A', 'B'] as $kode) {
        $kelas = $this->tournament->weightClasses()
            ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', $kode)->firstOrFail();

        Registration::factory()->for($this->kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
    }

    expect($this->tournament->weightClasses()->count())->toBeGreaterThan(100);

    $isi = $this->get('/login')->assertOk()->getContent();

    // Angkanya diambil dari baris yang berlabel, bukan dari mana pun di halaman.
    preg_match('/tabular-nums">(\d+)<\/span>\s*<span[^>]*>kelas dipertandingkan/', $isi, $cocok);

    expect($cocok[1] ?? null)->toBe('2');
});

it('tidak mengumumkan seluruh katalog saat belum ada peserta', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee((string) $this->tournament->weightClasses()->count());
});
