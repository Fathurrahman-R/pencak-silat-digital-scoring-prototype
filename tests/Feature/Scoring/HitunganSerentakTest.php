<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightIn;
use App\Support\Scoring\AlasanMenang;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;

/*
 * Pasal 11.6.e.2.c.(b) dan (c): kedua pesilat sama-sama tidak bisa bangkit.
 *
 *   belum ada nilai, di babak I  -> ditimbang, yang lebih ringan menang
 *   sudah ada nilai              -> nilai terbanyak
 *
 * Sebelum ini `HitunganTeknik` hanya bisa mencatat untuk SATU sudut, dan
 * `tentukanPemenangAngka()` tidak mengenal keadaan ini sama sekali.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = function (float $berat) use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $atlet = Athlete::factory()->for($kontingen)->create();
        $reg->athletes()->attach($atlet);

        WeightIn::create([
            'registration_id' => $reg->id, 'athlete_id' => $atlet->id,
            'weight' => $berat, 'passed' => true, 'weighed_at' => now(),
        ]);

        return $reg->refresh();
    };

    // Biru sengaja lebih ringan -- dialah yang harus menang lewat berat badan.
    $this->merah = $daftar(57.5);
    $this->biru = $daftar(56.0);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $this->merah->id, 'blue_registration_id' => $this->biru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->wasit = User::factory()->create();
    $this->timer = new MatchTimer;
    $this->hitungan = new HitunganTeknik(new TanggaHukuman($this->timer), $this->timer);
    $this->kalkulator = new TandingScoreCalculator;
    $this->mutlak = (int) config('scoring.tanding.hitungan_teknik.mutlak_pada_hitungan');
});

it('mencatat hitungan teknik untuk kedua sudut sekaligus', function () {
    $baris = $this->hitungan->catatSerentak($this->match, 1, 8, $this->wasit);

    expect($baris)->toHaveCount(2)
        ->and($this->match->technicalCounts()->count())->toBe(2)
        ->and($this->match->technicalCounts()->pluck('corner')->unique())->toHaveCount(2);
});

/*
 * Hitungan serentak TIDAK mengakhiri partai sendiri meski mencapai sepuluh.
 * Naskah menyuruh "mempertimbangkan faktor-faktor berikut" -- kalimat yang
 * tidak bisa dijalankan mesin sendirian.
 */
it('tidak mengakhiri partai sendiri saat hitungan serentak mencapai sepuluh', function () {
    $this->hitungan->catatSerentak($this->match, 1, $this->mutlak, $this->wasit);

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG)
        ->and($this->match->fresh()->winner_registration_id)->toBeNull();
});

it('menawarkan menang berat badan teringan saat belum ada nilai di babak I', function () {
    $this->hitungan->catatSerentak($this->match, 1, $this->mutlak, $this->wasit);

    $tawaran = $this->kalkulator->penyelesaianHitunganSerentak($this->match->fresh(), $this->mutlak);

    expect($tawaran)->toBe(['sebab' => 'berat_badan_teringan', 'pemenang' => Sudut::Biru->value]);
});

it('menawarkan menang nilai terbanyak setelah ada nilai tercatat', function () {
    ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => Sudut::Merah,
        'point_type' => 'tendangan', 'value' => 2, 'server_ts' => now(),
    ]);

    $this->hitungan->catatSerentak($this->match, 1, $this->mutlak, $this->wasit);

    $tawaran = $this->kalkulator->penyelesaianHitunganSerentak($this->match->fresh(), $this->mutlak);

    expect($tawaran)->toBe(['sebab' => 'nilai_terbanyak', 'pemenang' => Sudut::Merah->value]);
});

/*
 * Naskah tidak menyebut apa yang terjadi kalau nilainya pun sama. Yang jujur:
 * menawarkan tanpa pemenang, biar aparat yang memutus -- bukan menebak.
 */
it('menawarkan tanpa pemenang saat nilainya sama', function () {
    foreach ([Sudut::Merah, Sudut::Biru] as $sudut) {
        ScoreEvent::create([
            'match_id' => $this->match->id, 'round' => 1, 'corner' => $sudut,
            'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
        ]);
    }

    $this->hitungan->catatSerentak($this->match, 1, $this->mutlak, $this->wasit);

    expect($this->kalkulator->penyelesaianHitunganSerentak($this->match->fresh(), $this->mutlak))
        ->toBe(['sebab' => 'nilai_terbanyak', 'pemenang' => null]);
});

it('tidak menawarkan apa pun saat hanya satu sudut yang dihitung', function () {
    $this->hitungan->catat($this->match, Sudut::Merah, 1, $this->mutlak, $this->wasit);

    expect($this->kalkulator->penyelesaianHitunganSerentak($this->match->fresh(), $this->mutlak))
        ->toBeNull();
});

it('tidak menawarkan apa pun sebelum hitungan mencapai ambang mutlak', function () {
    $this->hitungan->catatSerentak($this->match, 1, $this->mutlak - 1, $this->wasit);

    expect($this->kalkulator->penyelesaianHitunganSerentak($this->match->fresh(), $this->mutlak))
        ->toBeNull();
});

it('menyebut kedua sebab kemenangan baru dengan bentuk terbaca', function () {
    $peta = AlasanMenang::peta();

    expect($peta)->toHaveKeys(['berat_badan_teringan', 'nilai_terbanyak'])
        ->and($peta['berat_badan_teringan'])->toContain('Berat Badan');
});
