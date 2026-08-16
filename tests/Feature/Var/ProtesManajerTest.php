<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\ManagerProtest;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Var\KeputusanProtesManajer;
use App\Support\Var\PengajuanProtesManajer;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($kontingen)->create());
    $regBiru = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_SELESAI, 'winner_registration_id' => $regMerah->id, 'win_reason' => 'angka',
    ]);

    $this->ketuaPertandingan = User::factory()->create();
    $this->delegasiTeknik = User::factory()->create();
    $this->ajukan = new PengajuanProtesManajer;
    $this->putuskan = new KeputusanProtesManajer;
});

it('mengajukan protes tingkat pertama dengan tenggat dari konfigurasi', function () {
    $protes = ($this->ajukan)->pertama($this->match, 'hasil dianggap keliru');

    expect($protes->level)->toBe(ManagerProtest::TINGKAT_PERTAMA)
        ->and((int) abs($protes->diajukan_at->diffInMinutes($protes->tenggat_keputusan_at)))
        ->toBe(config('scoring.protes_manajer.tingkat_pertama.keputusan_menit'));
});

it('menolak protes tingkat pertama kedua untuk partai yang sama', function () {
    ($this->ajukan)->pertama($this->match, 'alasan 1');

    expect(fn () => ($this->ajukan)->pertama($this->match, 'alasan 2'))
        ->toThrow(RuntimeException::class);
});

it('menolak banding sebelum tingkat pertama diputuskan', function () {
    $pertama = ($this->ajukan)->pertama($this->match, 'alasan');

    expect(fn () => ($this->ajukan)->banding($pertama, 'naik banding'))
        ->toThrow(RuntimeException::class);
});

it('mengajukan banding setelah tingkat pertama diputuskan', function () {
    $pertama = ($this->ajukan)->pertama($this->match, 'alasan');
    ($this->putuskan)($pertama, ManagerProtest::DITOLAK, 'tidak cukup bukti', $this->ketuaPertandingan);

    $banding = ($this->ajukan)->banding($pertama->fresh(), 'naik banding ke delegasi teknik');

    expect($banding->level)->toBe(ManagerProtest::BANDING)
        ->and($banding->parent_id)->toBe($pertama->id);
});

it('keputusan banding bersifat final', function () {
    $pertama = ($this->ajukan)->pertama($this->match, 'alasan');
    ($this->putuskan)($pertama, ManagerProtest::DITOLAK, null, $this->ketuaPertandingan);
    $banding = ($this->ajukan)->banding($pertama->fresh(), 'naik banding');

    expect($banding->final())->toBeFalse();

    ($this->putuskan)($banding, ManagerProtest::DITERIMA, 'dikabulkan', $this->delegasiTeknik);

    expect($banding->fresh()->final())->toBeTrue();
});

it('menolak memutuskan protes yang sudah diputuskan', function () {
    $pertama = ($this->ajukan)->pertama($this->match, 'alasan');
    ($this->putuskan)($pertama, ManagerProtest::DITERIMA, null, $this->ketuaPertandingan);

    expect(fn () => ($this->putuskan)($pertama, ManagerProtest::DITOLAK, null, $this->ketuaPertandingan))
        ->toThrow(RuntimeException::class);
});
