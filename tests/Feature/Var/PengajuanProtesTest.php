<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Var\KeputusanVar;
use App\Support\Var\PengajuanProtes;

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
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->pelatih = User::factory()->create();
    $this->wasitKomisiProtes = User::factory()->create();
    $this->ajukan = new PengajuanProtes;
    $this->putuskan = new KeputusanVar;
});

it('mengajukan protes pertama dan memakai satu kartu', function () {
    $review = ($this->ajukan)($this->match, Sudut::Merah, 1, 'jatuhan tidak dihitung', $this->pelatih);

    expect($review->corner)->toBe(Sudut::Merah)
        ->and($review->kejadian)->toBe('jatuhan tidak dihitung')
        ->and($review->sudahDiputuskan())->toBeFalse();

    $kartu = $review->protestCard;
    expect($kartu->corner)->toBe(Sudut::Merah)
        ->and($kartu->jumlah_dipakai)->toBe(1)
        ->and($kartu->sisaKartu())->toBe(1);
});

it('tenggat keputusan 5 menit sejak diajukan', function () {
    $review = ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian', $this->pelatih);

    expect((int) abs($review->tenggat_at->diffInSeconds($review->diajukan_at)))
        ->toBe(config('scoring.var.tenggat_keputusan_detik'));
});

it('menolak protes ketiga karena kartu sudah habis', function () {
    ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian 1', $this->pelatih);
    ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian 2', $this->pelatih);

    expect(fn () => ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian 3', $this->pelatih))
        ->toThrow(RuntimeException::class);
});

it('kartu sudut merah dan biru terpisah', function () {
    ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian 1', $this->pelatih);
    ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian 2', $this->pelatih);

    $review = ($this->ajukan)($this->match, Sudut::Biru, 1, 'kejadian sudut biru', $this->pelatih);

    expect($review->protestCard->corner)->toBe(Sudut::Biru)
        ->and($review->protestCard->jumlah_dipakai)->toBe(1);
});

it('memutuskan sah tanpa membatalkan apa pun', function () {
    $review = ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian', $this->pelatih);

    $diputuskan = ($this->putuskan)($review, 'sah', 'video mengonfirmasi', $this->wasitKomisiProtes);

    expect($diputuskan->keputusan)->toBe('sah')
        ->and($diputuskan->sudahDiputuskan())->toBeTrue()
        ->and($diputuskan->pemutus->is($this->wasitKomisiProtes))->toBeTrue();
});

it('memutuskan tidak sah membatalkan nilai yang disengketakan lewat baris pembatal', function () {
    $nilai = ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
        'point_type' => JenisSerangan::Pukulan, 'value' => 1, 'server_ts' => now(),
    ]);

    $review = ($this->ajukan)($this->match, Sudut::Biru, 1, 'nilai seharusnya tidak sah', $this->pelatih, scoreEvent: $nilai);
    ($this->putuskan)($review, 'tidak_sah', 'tidak berkaidah', $this->wasitKomisiProtes);

    $nilai->refresh();
    expect($nilai->dibatalkan())->toBeTrue()
        ->and($nilai->void_reason)->toContain('VAR');
});

it('memutuskan tidak sah membatalkan hukuman yang disengketakan', function () {
    $hukuman = Penalty::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
        'tier' => 'teguran', 'level' => 1, 'points' => -1,
        'violation_level' => 'sedang', 'created_by' => $this->wasitKomisiProtes->id,
    ]);

    $review = ($this->ajukan)($this->match, Sudut::Merah, 1, 'teguran tidak berdasar', $this->pelatih, penalty: $hukuman);
    ($this->putuskan)($review, 'tidak_sah', null, $this->wasitKomisiProtes);

    expect($hukuman->refresh()->dibatalkan())->toBeTrue();
});

it('menolak memutuskan protes yang sudah diputuskan', function () {
    $review = ($this->ajukan)($this->match, Sudut::Merah, 1, 'kejadian', $this->pelatih);
    ($this->putuskan)($review, 'sah', null, $this->wasitKomisiProtes);

    expect(fn () => ($this->putuskan)($review, 'tidak_sah', null, $this->wasitKomisiProtes))
        ->toThrow(RuntimeException::class);
});
