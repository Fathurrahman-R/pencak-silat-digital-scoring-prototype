<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\HitunganTeknik;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TanggaHukuman;

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

    $this->regBiru = $regBiru;

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->wasit = User::factory()->create();
    $this->hitung = new HitunganTeknik(new TanggaHukuman(new MatchTimer), new MatchTimer);
});

it('mencatat hitungan tanpa efek lain di bawah ambang teguran', function () {
    $h = $this->hitung->catat($this->match, Sudut::Biru, 1, 5, $this->wasit);

    expect($h->count_reached)->toBe(5)
        ->and(Penalty::count())->toBe(0)
        ->and($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menjatuhkan Teguran I saat hitungan mencapai sembilan', function () {
    $this->hitung->catat($this->match, Sudut::Biru, 1, 9, $this->wasit);

    $penalty = Penalty::firstOrFail();
    expect($penalty->tier)->toBe(TingkatHukuman::Teguran)
        ->and($penalty->level)->toBe(1)
        ->and($penalty->corner)->toBe(Sudut::Biru);
});

it('mengakhiri partai dengan menang mutlak saat hitungan mencapai sepuluh', function () {
    $this->hitung->catat($this->match, Sudut::Biru, 1, 10, $this->wasit);

    $partai = $this->match->fresh();
    expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($partai->win_reason)->toBe('mutlak')
        ->and($partai->winner_registration_id)->toBe($partai->red_registration_id)
        // Teguran I tetap tercatat meski partai berakhir mutlak pada hitungan yang sama.
        ->and(Penalty::where('tier', TingkatHukuman::Teguran)->count())->toBe(1);
});

it('memenangkan teknik untuk lawan setelah tiga hitungan beruntun dalam satu babak', function () {
    $this->hitung->catat($this->match, Sudut::Biru, 1, 5, $this->wasit);
    $this->hitung->catat($this->match, Sudut::Biru, 1, 6, $this->wasit);
    $this->hitung->catat($this->match, Sudut::Biru, 1, 4, $this->wasit);

    $partai = $this->match->fresh();
    expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($partai->win_reason)->toBe('teknik')
        ->and($partai->winner_registration_id)->toBe($partai->red_registration_id);
});

it('tidak menghitung beruntun bila diselingi hitungan terhadap sudut lain', function () {
    $this->hitung->catat($this->match, Sudut::Biru, 1, 5, $this->wasit);
    $this->hitung->catat($this->match, Sudut::Merah, 1, 4, $this->wasit);
    $this->hitung->catat($this->match, Sudut::Biru, 1, 5, $this->wasit);

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak hitungan di luar rentang satu sampai sepuluh', function () {
    $this->hitung->catat($this->match, Sudut::Biru, 1, 11, $this->wasit);
})->throws(RuntimeException::class, 'antara 1 dan 10');
