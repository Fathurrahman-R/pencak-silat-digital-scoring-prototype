<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusDeduction;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Jurus\JurusScoreCalculator;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->registrasi = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
    $this->registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $this->performance = JurusPerformance::create([
        'jurus_event_id' => $this->event->id,
        'registration_id' => $this->registrasi->id,
        'tahap' => 'final',
    ]);

    $this->kalkulator = new JurusScoreCalculator;

    $this->beriNilai = function (array $nilai) {
        foreach ($nilai as $v) {
            JurusScore::create([
                'performance_id' => $this->performance->id,
                'judge_user_id' => User::factory()->create()->id,
                'value' => $v,
            ]);
        }
    };
});

it('median dari 6 juri adalah rata-rata dua nilai tengah', function () {
    ($this->beriNilai)([9.50, 9.60, 9.70, 9.72, 9.80, 9.90]);

    // terurut: 9.50 9.60 9.70 9.72 9.80 9.90 -> tengah (9.70+9.72)/2 = 9.71
    expect($this->kalkulator->median($this->performance))->toBe(9.71);
});

it('median dari 4 juri adalah rata-rata dua nilai tengah', function () {
    ($this->beriNilai)([9.60, 9.80, 9.70, 9.90]);

    // terurut: 9.60 9.70 9.80 9.90 -> (9.70+9.80)/2 = 9.75
    expect($this->kalkulator->median($this->performance))->toBe(9.75);
});

it('median tidak terpengaruh nilai kembar', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);

    expect($this->kalkulator->median($this->performance))->toBe(9.70);
});

it('skor akhir mengurangi pengurangan juri dan pengawas dari median', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);

    JurusDeduction::create([
        'performance_id' => $this->performance->id, 'tier' => 'juri',
        'alasan' => 'gerakan tertinggal', 'jumlah' => 0.01,
    ]);
    JurusDeduction::create([
        'performance_id' => $this->performance->id, 'tier' => 'pengawas',
        'alasan' => 'keluar gelanggang', 'jumlah' => 0.50,
    ]);

    // 9.70 - 0.01 - 0.50 = 9.19
    expect($this->kalkulator->skorAkhir($this->performance))->toBe(9.19);
});

it('pengurangan yang dibatalkan tidak ikut mengurangi skor', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);

    $d = JurusDeduction::create([
        'performance_id' => $this->performance->id, 'tier' => 'juri',
        'alasan' => 'kesalahan urutan', 'jumlah' => 0.01,
    ]);
    $d->update(['voided_at' => now(), 'void_reason' => 'salah catat']);

    expect($this->kalkulator->skorAkhir($this->performance))->toBe(9.70);
});

it('diskualifikasi menghasilkan skor 0,00 walau ada nilai juri', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);
    $this->performance->update(['didiskualifikasi' => true]);

    expect($this->kalkulator->skorAkhir($this->performance->fresh()))->toBe(0.0);
});

it('standar deviasi lebih rendah untuk nilai yang lebih rapat', function () {
    $rapat = JurusPerformance::create([
        'jurus_event_id' => $this->event->id, 'registration_id' => $this->registrasi->id, 'tahap' => 'penyisihan',
    ]);
    foreach ([9.70, 9.71, 9.69, 9.70] as $v) {
        JurusScore::create(['performance_id' => $rapat->id, 'judge_user_id' => User::factory()->create()->id, 'value' => $v]);
    }

    ($this->beriNilai)([9.00, 10.00, 9.00, 10.00]);

    expect($this->kalkulator->standarDeviasi($rapat))->toBeLessThan($this->kalkulator->standarDeviasi($this->performance));
});

it('peringkat mengurutkan skor tertinggi lebih dulu', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);

    $kedua = JurusPerformance::create([
        'jurus_event_id' => $this->event->id, 'registration_id' => $this->registrasi->id, 'tahap' => 'penyisihan',
    ]);
    foreach ([9.90, 9.90, 9.90, 9.90] as $v) {
        JurusScore::create(['performance_id' => $kedua->id, 'judge_user_id' => User::factory()->create()->id, 'value' => $v]);
    }

    $peringkat = $this->kalkulator->peringkat(collect([$this->performance, $kedua]));

    expect($peringkat->first()->id)->toBe($kedua->id);
});

it('skor akhir sama diselesaikan lewat hukuman terendah', function () {
    ($this->beriNilai)([9.70, 9.70, 9.70, 9.70]);
    JurusDeduction::create(['performance_id' => $this->performance->id, 'tier' => 'juri', 'alasan' => 'a', 'jumlah' => 0.01]);
    // median 9.70 - 0.01 = skor akhir 9.69

    $tanpaHukuman = JurusPerformance::create([
        'jurus_event_id' => $this->event->id, 'registration_id' => $this->registrasi->id, 'tahap' => 'penyisihan',
    ]);
    foreach ([9.69, 9.69, 9.69, 9.69] as $v) {
        JurusScore::create(['performance_id' => $tanpaHukuman->id, 'judge_user_id' => User::factory()->create()->id, 'value' => $v]);
    }
    // median 9.69, tanpa pengurangan -> skor akhir 9.69, seri dengan performance

    expect($this->kalkulator->skorAkhir($this->performance))->toBe(9.69)
        ->and($this->kalkulator->skorAkhir($tanpaHukuman))->toBe(9.69);

    $peringkat = $this->kalkulator->peringkat(collect([$this->performance, $tanpaHukuman]));

    expect($peringkat->first()->id)->toBe($tanpaHukuman->id);
});
