<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Rekap\RekapMedali;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);
    $this->rekap = app(RekapMedali::class);
});

it('menentukan emas dan perak dari partai final serta perunggu dari kedua semifinal', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $buatPeserta = function (string $nama) use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => $nama]));

        return $reg;
    };

    $juara1 = $buatPeserta('Juara Satu');
    $juara2 = $buatPeserta('Juara Dua');
    $perunggu1 = $buatPeserta('Perunggu Satu');
    $perunggu2 = $buatPeserta('Perunggu Dua');

    // Semifinal 1: juara1 (red) vs perunggu1 (blue) -> juara1 menang
    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $juara1->id, 'blue_registration_id' => $perunggu1->id,
        'winner_registration_id' => $juara1->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI, 'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
    ]);
    // Semifinal 2: juara2 (red) vs perunggu2 (blue) -> juara2 menang
    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 2,
        'red_registration_id' => $juara2->id, 'blue_registration_id' => $perunggu2->id,
        'winner_registration_id' => $juara2->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI, 'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
    ]);
    // Final: juara1 (red) vs juara2 (blue) -> juara1 menang
    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 2, 'position' => 1,
        'red_registration_id' => $juara1->id, 'blue_registration_id' => $juara2->id,
        'winner_registration_id' => $juara1->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI, 'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
    ]);

    $hasil = $this->rekap->tanding($this->tournament)->firstWhere('kelas.id', $kelas->id);

    expect($hasil['emas']->id)->toBe($juara1->id)
        ->and($hasil['perak']->id)->toBe($juara2->id)
        ->and($hasil['perunggu']->pluck('id')->sort()->values()->all())
        ->toBe(collect([$perunggu1->id, $perunggu2->id])->sort()->values()->all());
});

it('tidak menghasilkan medali dari final yang belum disahkan', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'D')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $a = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $a->athletes()->attach(Athlete::factory()->for($kontingen)->create());
    $b = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $b->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $a->id, 'blue_registration_id' => $b->id,
        'winner_registration_id' => $a->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI,
    ]);

    expect($this->rekap->tanding($this->tournament)->firstWhere('kelas.id', $kelas->id))->toBeNull();
});

it('menentukan medali jurus dari tiga peringkat teratas yang sudah disahkan', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $buatPenampilan = function (string $nama, float $nilai) use ($kontingen, $event) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()->create(['jurus_event_id' => $event->id, 'weight_class_id' => null]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => $nama]));
        $perf = JurusPerformance::create([
            'jurus_event_id' => $event->id, 'registration_id' => $reg->id, 'tahap' => 'final',
            'status' => JurusPerformance::STATUS_SELESAI, 'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
        ]);
        JurusScore::create(['performance_id' => $perf->id, 'judge_user_id' => User::factory()->create()->id, 'value' => $nilai]);

        return $reg;
    };

    $satu = $buatPenampilan('Nilai Tertinggi', 9.80);
    $dua = $buatPenampilan('Nilai Kedua', 9.70);
    $tiga = $buatPenampilan('Nilai Ketiga', 9.60);
    $empat = $buatPenampilan('Nilai Keempat', 9.50);

    $hasil = $this->rekap->jurus($this->tournament)->firstWhere('nomor.id', $event->id);

    expect($hasil['emas']->id)->toBe($satu->id)
        ->and($hasil['perak']->id)->toBe($dua->id)
        ->and($hasil['perunggu']->id)->toBe($tiga->id);
});

it('peringkat umum mengurutkan kontingen berdasarkan emas lalu perak lalu perunggu', function () {
    $kontingenA = Contingent::factory()->for($this->tournament)->create(['name' => 'Kontingen A']);
    $kontingenB = Contingent::factory()->for($this->tournament)->create(['name' => 'Kontingen B']);

    $kelas1 = $this->tournament->weightClasses()->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $kelas2 = $this->tournament->weightClasses()->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'D')->firstOrFail();

    $buatFinalDua = function ($kelas, $kontingenEmas, $kontingenPerak) {
        $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);
        $emas = Registration::factory()->for($kontingenEmas)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $emas->athletes()->attach(Athlete::factory()->for($kontingenEmas)->create());
        $perak = Registration::factory()->for($kontingenPerak)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $perak->athletes()->attach(Athlete::factory()->for($kontingenPerak)->create());

        SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
            'red_registration_id' => $emas->id, 'blue_registration_id' => $perak->id,
            'winner_registration_id' => $emas->id, 'win_reason' => 'angka',
            'status' => SilatMatch::STATUS_SELESAI, 'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
        ]);
    };

    // Kontingen A menang 2 emas, Kontingen B menang 0 emas (2 perak)
    $buatFinalDua($kelas1, $kontingenA, $kontingenB);
    $buatFinalDua($kelas2, $kontingenA, $kontingenB);

    $peringkat = $this->rekap->peringkatUmum($this->tournament);

    expect($peringkat->first()['kontingen'])->toBe('Kontingen A')
        ->and($peringkat->first()['emas'])->toBe(2)
        ->and($peringkat->last()['kontingen'])->toBe('Kontingen B')
        ->and($peringkat->last()['perak'])->toBe(2);
});
