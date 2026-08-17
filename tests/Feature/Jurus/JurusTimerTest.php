<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Support\Jurus\JurusTimer;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $registrasi = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['jurus_event_id' => $event->id, 'weight_class_id' => null]);
    $registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $this->performance = JurusPerformance::create([
        'jurus_event_id' => $event->id, 'registration_id' => $registrasi->id, 'tahap' => 'final',
    ]);

    $this->timer = new JurusTimer;
});

it('memulai penampilan mencatat waktu mulai server', function () {
    $mulai = $this->timer->mulai($this->performance);

    expect($mulai->status)->toBe(JurusPerformance::STATUS_BERLANGSUNG)
        ->and($mulai->started_at)->not->toBeNull();
});

it('menghentikan penampilan mencatat lama tampil dalam milidetik', function () {
    $this->travelTo(now());
    $this->timer->mulai($this->performance);

    $this->travel(95)->seconds();
    $selesai = $this->timer->berhenti($this->performance->fresh());

    expect($selesai->status)->toBe(JurusPerformance::STATUS_SELESAI)
        ->and($selesai->duration_ms)->toBeGreaterThanOrEqual(95_000)
        ->and($selesai->duration_ms)->toBeLessThan(96_000);
});

it('menolak menghentikan penampilan yang belum dimulai', function () {
    expect(fn () => $this->timer->berhenti($this->performance))->toThrow(RuntimeException::class);
});

it('menolak memulai penampilan yang sudah berjalan', function () {
    $this->timer->mulai($this->performance);

    expect(fn () => $this->timer->mulai($this->performance->fresh()))->toThrow(RuntimeException::class);
});
