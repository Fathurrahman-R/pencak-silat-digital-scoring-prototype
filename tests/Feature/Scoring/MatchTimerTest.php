<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusBabak;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchRound;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Scoring\MatchTimer;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftarkan = function () use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $reg;
    };

    $r1 = $daftarkan();
    $r2 = $daftarkan();
    $r3 = $daftarkan();
    $r4 = $daftarkan();

    $this->partaiSatu = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $r1->id, 'blue_registration_id' => $r2->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);
    $this->partaiDua = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 2,
        'red_registration_id' => $r3->id, 'blue_registration_id' => $r4->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);
    $this->final = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 2, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->pemenangSatu = $r1;
    $this->timer = new MatchTimer;
});

it('memulai babak pertama dan menandai partai berlangsung', function () {
    $round = $this->timer->mulaiBabak($this->partaiSatu, 1);

    expect($round->status)->toBe(StatusBabak::Berjalan)
        ->and($round->started_at)->not->toBeNull()
        ->and($round->duration_ms)->toBe(config('scoring.tanding.babak.'.GolonganUsia::Dewasa->value.'.durasi_ms'))
        ->and($this->partaiSatu->fresh()->current_round)->toBe(1)
        ->and($this->partaiSatu->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak memulai babak yang sudah berjalan', function () {
    $this->timer->mulaiBabak($this->partaiSatu, 1);

    $this->timer->mulaiBabak($this->partaiSatu, 1);
})->throws(RuntimeException::class, 'sudah dimulai');

it('menolak memulai babak berikutnya sebelum babak sekarang selesai', function () {
    $this->timer->mulaiBabak($this->partaiSatu, 1);

    $this->timer->mulaiBabak($this->partaiSatu, 2);
})->throws(RuntimeException::class, 'belum selesai');

it('mengizinkan babak berikutnya setelah babak sekarang diselesaikan', function () {
    $round1 = $this->timer->mulaiBabak($this->partaiSatu, 1);
    $this->timer->selesaikanBabak($round1);

    $round2 = $this->timer->mulaiBabak($this->partaiSatu, 2);

    expect($round2->status)->toBe(StatusBabak::Berjalan)
        ->and($this->partaiSatu->fresh()->current_round)->toBe(2);
});

it('mengakumulasi waktu terpakai saat dijeda', function () {
    $round = $this->timer->mulaiBabak($this->partaiSatu, 1);
    $round->update(['started_at' => now()->subSeconds(10)]);

    $dijeda = $this->timer->jeda($round->fresh());

    expect($dijeda->status)->toBe(StatusBabak::Jeda)
        ->and($dijeda->accumulated_ms)->toBeGreaterThanOrEqual(10_000)
        ->and($dijeda->started_at)->toBeNull();
});

it('menolak menjeda babak yang tidak sedang berjalan', function () {
    $round = MatchRound::create([
        'match_id' => $this->partaiSatu->id, 'round' => 1,
        'duration_ms' => 120_000, 'status' => StatusBabak::BelumMulai,
    ]);

    $this->timer->jeda($round);
})->throws(RuntimeException::class, 'tidak sedang berjalan');

it('melanjutkan babak yang dijeda tanpa kehilangan waktu yang sudah terpakai', function () {
    $round = $this->timer->mulaiBabak($this->partaiSatu, 1);
    $round->update(['started_at' => now()->subSeconds(10)]);
    $dijeda = $this->timer->jeda($round->fresh());

    $dilanjutkan = $this->timer->lanjutkan($dijeda);

    expect($dilanjutkan->status)->toBe(StatusBabak::Berjalan)
        ->and($dilanjutkan->waktuTerpakaiMs())->toBeGreaterThanOrEqual(10_000);
});

it('mereset babak ke keadaan semula', function () {
    $round = $this->timer->mulaiBabak($this->partaiSatu, 1);
    $round->update(['started_at' => now()->subSeconds(10)]);

    $direset = $this->timer->reset($round->fresh());

    expect($direset->status)->toBe(StatusBabak::BelumMulai)
        ->and($direset->accumulated_ms)->toBe(0)
        ->and($direset->started_at)->toBeNull();
});

it('mengakhiri partai, mengesampingkan babak yang masih berjalan, dan menaikkan pemenang ke bagan berikutnya', function () {
    $round = $this->timer->mulaiBabak($this->partaiSatu, 1);

    $selesai = $this->timer->akhiriPartai($this->partaiSatu, $this->pemenangSatu, 'mutlak');

    expect($selesai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($selesai->winner_registration_id)->toBe($this->pemenangSatu->id)
        ->and($selesai->win_reason)->toBe('mutlak')
        ->and($round->fresh()->status)->toBe(StatusBabak::Selesai)
        ->and($this->final->fresh()->red_registration_id)->toBe($this->pemenangSatu->id);
});

it('menolak memulai babak pada partai yang sudah selesai', function () {
    $this->timer->akhiriPartai($this->partaiSatu, $this->pemenangSatu, 'mutlak');

    $this->timer->mulaiBabak($this->partaiSatu, 1);
})->throws(RuntimeException::class, 'sudah selesai');
