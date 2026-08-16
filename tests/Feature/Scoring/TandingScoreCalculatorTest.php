<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchRound;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\WeightIn;
use App\Support\Scoring\TandingScoreCalculator;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->atletMerah = Athlete::factory()->for($this->kontingen)->create(['name' => 'Merah']);
    $this->regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $this->regMerah->athletes()->attach($this->atletMerah);

    $this->atletBiru = Athlete::factory()->for($this->kontingen)->create(['name' => 'Biru']);
    $this->regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $this->regBiru->athletes()->attach($this->atletBiru);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $this->regMerah->id, 'blue_registration_id' => $this->regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->kalkulator = new TandingScoreCalculator;

    $this->nilai = function (Sudut $sudut, int $value, int $babak = 1) {
        return ScoreEvent::create([
            'match_id' => $this->match->id, 'round' => $babak, 'corner' => $sudut,
            'point_type' => 'pukulan', 'value' => $value, 'server_ts' => now(),
        ]);
    };

    $this->hukuman = function (Sudut $sudut, int $points, int $babak = 1) {
        return Penalty::create([
            'match_id' => $this->match->id, 'round' => $babak, 'corner' => $sudut,
            'tier' => 'teguran', 'level' => 1, 'points' => $points, 'violation_level' => 'sedang',
        ]);
    };
});

it('menghitung skor dari total nilai dikurangi hukuman', function () {
    ($this->nilai)(Sudut::Merah, 2);
    ($this->nilai)(Sudut::Merah, 3);
    ($this->hukuman)(Sudut::Merah, -1);

    expect($this->kalkulator->skor($this->match, Sudut::Merah))->toBe(4);
});

it('mengabaikan score_event dan penalty yang sudah dibatalkan', function () {
    $dibatalkan = ($this->nilai)(Sudut::Merah, 3);
    $dibatalkan->update(['voided_at' => now(), 'void_reason' => 'koreksi dewan juri']);
    ($this->nilai)(Sudut::Merah, 1);

    expect($this->kalkulator->skor($this->match, Sudut::Merah))->toBe(1);
});

it('memisahkan skor per babak dari skor total', function () {
    ($this->nilai)(Sudut::Merah, 2, babak: 1);
    ($this->nilai)(Sudut::Merah, 3, babak: 2);

    expect($this->kalkulator->skorBabak($this->match, Sudut::Merah, 1))->toBe(2)
        ->and($this->kalkulator->skorBabak($this->match, Sudut::Merah, 2))->toBe(3)
        ->and($this->kalkulator->skor($this->match, Sudut::Merah))->toBe(5);
});

it('menentukan menang angka murni saat skor berbeda', function () {
    ($this->nilai)(Sudut::Merah, 3);
    ($this->nilai)(Sudut::Biru, 1);

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->pemenang)->toBe(Sudut::Merah)
        ->and($hasil->sebab)->toBe('angka')
        ->and($hasil->skorMerah)->toBe(3)
        ->and($hasil->skorBiru)->toBe(1);
});

it('memecah seri lewat hukuman terendah saat skor sama', function () {
    ($this->nilai)(Sudut::Merah, 2);
    ($this->nilai)(Sudut::Biru, 3);
    ($this->hukuman)(Sudut::Biru, -1); // skor biru jadi 2, sama dengan merah

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->skorMerah)->toBe($hasil->skorBiru)
        ->and($hasil->sebab)->toBe('hukuman_terendah')
        ->and($hasil->pemenang)->toBe(Sudut::Merah);
});

it('memecah seri lewat nilai prestasi tertinggi urutan 3-2-1 saat hukuman juga sama', function () {
    // Skor total sama-sama 3, hukuman sama-sama nol, tapi merah punya satu
    // jatuhan (nilai 3) sedangkan biru tiga pukulan (nilai 1 x 3).
    ($this->nilai)(Sudut::Merah, 3);
    ($this->nilai)(Sudut::Biru, 1);
    ($this->nilai)(Sudut::Biru, 1);
    ($this->nilai)(Sudut::Biru, 1);

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->skorMerah)->toBe($hasil->skorBiru)
        ->and($hasil->sebab)->toBe('nilai_prestasi_tertinggi')
        ->and($hasil->pemenang)->toBe(Sudut::Merah);
});

it('meminta babak tambahan saat seri sampai tiga tingkat pemecah pertama', function () {
    ($this->nilai)(Sudut::Merah, 2);
    ($this->nilai)(Sudut::Biru, 2);

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->sebab)->toBe('perlu_babak_tambahan')
        ->and($hasil->pemenang)->toBeNull();
});

it('memecah seri lewat berat badan teringan setelah babak tambahan tetap seri', function () {
    ($this->nilai)(Sudut::Merah, 2);
    ($this->nilai)(Sudut::Biru, 2);

    // Babak tambahan (babak 4, melebihi jumlah normal 3 untuk Dewasa) sudah
    // dimainkan dan skornya tetap seri.
    MatchRound::create(['match_id' => $this->match->id, 'round' => 4, 'duration_ms' => 120_000, 'status' => 'selesai']);

    WeightIn::create([
        'registration_id' => $this->regMerah->id, 'athlete_id' => $this->atletMerah->id,
        'weight' => 58.0, 'passed' => true, 'weighed_at' => now(),
    ]);
    WeightIn::create([
        'registration_id' => $this->regBiru->id, 'athlete_id' => $this->atletBiru->id,
        'weight' => 59.5, 'passed' => true, 'weighed_at' => now(),
    ]);

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->sebab)->toBe('berat_badan_teringan')
        ->and($hasil->pemenang)->toBe(Sudut::Merah);
});

it('meminta undian saat semua pemecah seri tidak juga memisahkan', function () {
    ($this->nilai)(Sudut::Merah, 2);
    ($this->nilai)(Sudut::Biru, 2);
    MatchRound::create(['match_id' => $this->match->id, 'round' => 4, 'duration_ms' => 120_000, 'status' => 'selesai']);

    $hasil = $this->kalkulator->tentukanPemenangAngka($this->match);

    expect($hasil->sebab)->toBe('perlu_undian')
        ->and($hasil->pemenang)->toBeNull();
});

it('menawarkan menang WMP saat selisih mencapai 30 pada babak II', function () {
    $this->match->update(['current_round' => 2]);
    ($this->nilai)(Sudut::Merah, 30, babak: 2);

    expect($this->kalkulator->cekTawaranWmp($this->match))->toBe(Sudut::Merah);
});

it('tidak menawarkan menang WMP sebelum babak yang disyaratkan', function () {
    $this->match->update(['current_round' => 1]);
    ($this->nilai)(Sudut::Merah, 30, babak: 1);

    expect($this->kalkulator->cekTawaranWmp($this->match))->toBeNull();
});

it('memakai ambang selisih lebih rendah untuk golongan usia dini', function () {
    $kelasUsiaDini = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::UsiaDini2, JenisKelamin::Putra)->where('code', 'A')->firstOrFail();
    $bracketUd = Bracket::create(['weight_class_id' => $kelasUsiaDini->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelasUsiaDini->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());
    $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelasUsiaDini->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

    $partaiUd = SilatMatch::create([
        'bracket_id' => $bracketUd->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    ScoreEvent::create([
        'match_id' => $partaiUd->id, 'round' => 1, 'corner' => Sudut::Merah,
        'point_type' => 'jatuhan', 'value' => 20, 'server_ts' => now(),
    ]);

    // Usia Dini: selisih 20 sudah cukup, berlaku sejak babak pertama.
    expect($this->kalkulator->cekTawaranWmp($partaiUd))->toBe(Sudut::Merah);
});
