<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Enums\TingkatPelanggaran;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
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

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->wasit = User::factory()->create();
    $this->tangga = new TanggaHukuman(new MatchTimer);
});

it('mencatat pembinaan pertama tanpa pengurangan nilai', function () {
    $p = $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, 'menyerang setelah gong', $this->wasit);

    expect($p->tier)->toBe(TingkatHukuman::Pembinaan)
        ->and($p->level)->toBe(1)
        ->and($p->points)->toBe(0)
        ->and($this->tangga->jumlahPembinaan($this->match, Sudut::Merah))->toBe(1);
});

it('menaikkan pelanggaran ringan ketiga menjadi Teguran I dengan pengurangan satu', function () {
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);

    $ketiga = $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);

    expect($ketiga->tier)->toBe(TingkatHukuman::Teguran)
        ->and($ketiga->level)->toBe(1)
        ->and($ketiga->points)->toBe(-1);
});

it('memberikan pembinaan lagi setelah eskalasi ke teguran', function () {
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit); // -> Teguran I

    $lagi = $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);

    expect($lagi->tier)->toBe(TingkatHukuman::Pembinaan)
        ->and($lagi->level)->toBe(1);
});

it('menjatuhkan Teguran langsung untuk pelanggaran sedang tanpa melewati pembinaan', function () {
    $p = $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit);

    expect($p->tier)->toBe(TingkatHukuman::Teguran)
        ->and($p->level)->toBe(1)
        ->and($p->points)->toBe(-1);
});

it('menjatuhkan Peringatan I langsung untuk pelanggaran berat tanpa melewati teguran', function () {
    $p = $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit);

    expect($p->tier)->toBe(TingkatHukuman::Peringatan)
        ->and($p->level)->toBe(1)
        ->and($p->points)->toBe(-5);
});

it('menaikkan teguran ketiga dalam satu babak menjadi Peringatan I, bukan teguran', function () {
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit); // Teguran I
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit); // Teguran II

    $ketiga = $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit);

    expect($ketiga->tier)->toBe(TingkatHukuman::Peringatan)
        ->and($ketiga->level)->toBe(1)
        ->and($ketiga->points)->toBe(-5);
});

it('mereset hitungan teguran tiap babak baru', function () {
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Sedang, null, $this->wasit);

    // Babak baru -- teguran babak sebelumnya tidak ikut terbawa.
    $babakDua = $this->tangga->catat($this->match, Sudut::Biru, 2, TingkatPelanggaran::Sedang, null, $this->wasit);

    expect($babakDua->tier)->toBe(TingkatHukuman::Teguran)
        ->and($babakDua->level)->toBe(1);
});

it('tidak mereset peringatan saat babak berganti', function () {
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit); // Peringatan I

    $babakDua = $this->tangga->catat($this->match, Sudut::Biru, 2, TingkatPelanggaran::Berat, null, $this->wasit);

    expect($babakDua->tier)->toBe(TingkatHukuman::Peringatan)
        ->and($babakDua->level)->toBe(2)
        ->and($babakDua->points)->toBe(-10);
});

it('mengakhiri partai dengan diskualifikasi saat Peringatan III dijatuhkan', function () {
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit); // I
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit); // II

    $ketiga = $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit);

    expect($ketiga->tier)->toBe(TingkatHukuman::Peringatan)
        ->and($ketiga->level)->toBe(3)
        ->and($ketiga->points)->toBeNull()
        ->and($ketiga->diskualifikasi())->toBeTrue();

    $partai = $this->match->fresh();
    expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($partai->win_reason)->toBe('diskualifikasi')
        ->and($partai->winner_registration_id)->toBe($partai->red_registration_id);
});

it('menolak mencatat pelanggaran baru pada pesilat yang sudah didiskualifikasi', function () {
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Berat, null, $this->wasit);

    $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Ringan, null, $this->wasit);
})->throws(RuntimeException::class, 'didiskualifikasi');

it('tidak saling memengaruhi antara sudut merah dan biru', function () {
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);
    $this->tangga->catat($this->match, Sudut::Merah, 1, TingkatPelanggaran::Ringan, null, $this->wasit);

    $biru = $this->tangga->catat($this->match, Sudut::Biru, 1, TingkatPelanggaran::Ringan, null, $this->wasit);

    expect($biru->tier)->toBe(TingkatHukuman::Pembinaan)
        ->and($biru->level)->toBe(1);
});
