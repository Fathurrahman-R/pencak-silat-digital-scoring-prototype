<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeInput;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\ConsensusEvaluator;

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

    $this->juri = User::factory()->count(3)->create();
    $this->evaluator = new ConsensusEvaluator;

    $this->input = function (int $indeksJuri, Sudut $sudut, JenisSerangan $jenis, $ts) {
        return JudgeInput::create([
            'match_id' => $this->match->id,
            'round' => 1,
            'judge_user_id' => $this->juri[$indeksJuri]->id,
            'corner' => $sudut,
            'point_type' => $jenis,
            'server_ts' => $ts,
        ]);
    };
});

it('menerbitkan nilai saat dua dari tiga juri sepakat dalam window', function () {
    $t0 = now();

    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    expect($this->evaluator->evaluasi($a))->toBeNull();

    $b = ($this->input)(1, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(500));
    $hasil = $this->evaluator->evaluasi($b);

    expect($hasil)->not->toBeNull()
        ->and($hasil->value)->toBe(1)
        ->and($hasil->corner)->toBe(Sudut::Merah)
        ->and($a->fresh()->score_event_id)->toBe($hasil->id)
        ->and($b->fresh()->score_event_id)->toBe($hasil->id);
});

it('tidak menerbitkan nilai saat kesepakatan terjadi di luar window', function () {
    $t0 = now();

    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $this->evaluator->evaluasi($a);

    // Window bawaan 2000ms -- 2500ms sudah di luar jangkauan.
    $b = ($this->input)(1, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(2500));
    $hasil = $this->evaluator->evaluasi($b);

    expect($hasil)->toBeNull();
});

it('menghitung juri yang sama menekan dua kali sebagai satu suara', function () {
    $t0 = now();

    $a1 = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $this->evaluator->evaluasi($a1);

    $a2 = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(300));
    $hasil = $this->evaluator->evaluasi($a2);

    expect($hasil)->toBeNull()
        ->and(ScoreEvent::count())->toBe(0);
});

it('tetap benar walau baris tersimpan tidak berurutan menurut waktunya', function () {
    $t0 = now();

    // Baris ini dibuat lebih dulu (id lebih kecil) tapi server_ts-nya lebih
    // belakangan -- menirukan dua permintaan yang tiba tidak berurutan.
    $belakangan = ($this->input)(0, Sudut::Biru, JenisSerangan::Tendangan, $t0->clone()->addMilliseconds(800));
    $duluan = ($this->input)(1, Sudut::Biru, JenisSerangan::Tendangan, $t0);

    $hasil = $this->evaluator->evaluasi($belakangan);

    expect($hasil)->not->toBeNull()
        ->and($duluan->fresh()->score_event_id)->toBe($hasil->id);
});

it('tidak membentuk nilai kedua dari input yang sudah terpakai', function () {
    $t0 = now();

    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $b = ($this->input)(1, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(200));
    $this->evaluator->evaluasi($a);
    $pertama = $this->evaluator->evaluasi($b);
    expect($pertama)->not->toBeNull();

    // c sendirian -- a dan b sudah terpakai membentuk nilai pertama.
    $c = ($this->input)(2, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(400));
    $kedua = $this->evaluator->evaluasi($c);

    expect($kedua)->toBeNull()
        ->and(ScoreEvent::count())->toBe(1);
});

it('mengikuti ambang dan window dari setelan peraturan turnamen, bukan angka tetap', function () {
    $this->tournament->ruleSetting->update(['ambang_sepakat' => 3, 'window_konsensus_ms' => 5000]);

    $t0 = now();
    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $this->evaluator->evaluasi($a);

    // Dua dari tiga tidak lagi cukup -- ambang sekarang tiga.
    $b = ($this->input)(1, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(1000));
    expect($this->evaluator->evaluasi($b))->toBeNull();

    // 4500ms masih dalam window 5000ms yang baru.
    $c = ($this->input)(2, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(4500));
    expect($this->evaluator->evaluasi($c))->not->toBeNull();
});

it('tidak saling memengaruhi antar sudut dan jenis nilai yang berbeda', function () {
    $t0 = now();

    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $this->evaluator->evaluasi($a);

    $bedaSudut = ($this->input)(1, Sudut::Biru, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(200));
    expect($this->evaluator->evaluasi($bedaSudut))->toBeNull();

    $bedaJenis = ($this->input)(1, Sudut::Merah, JenisSerangan::Tendangan, $t0->clone()->addMilliseconds(300));
    expect($this->evaluator->evaluasi($bedaJenis))->toBeNull();

    // Pukulan-merah masih menunggu juri kedua yang benar-benar cocok kombinasinya.
    $cocok = ($this->input)(2, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(400));
    expect($this->evaluator->evaluasi($cocok))->not->toBeNull();
});

/*
 * PHP test berjalan satu proses -- tidak bisa mensimulasikan dua request HTTP
 * yang benar-benar paralel. Yang diuji di sini adalah jaminan idempotensinya:
 * evaluasi kedua input yang "nyaris bersamaan" tetap hanya melahirkan satu
 * score_event, apa pun urutan evaluasi dijalankan. Di produksi, SELECT ... FOR
 * UPDATE pada baris partai yang membuat urutan itu selalu serial.
 */
it('tetap menghasilkan satu score_event walau dua input tiba nyaris bersamaan', function () {
    $t0 = now();

    $a = ($this->input)(0, Sudut::Merah, JenisSerangan::Pukulan, $t0);
    $b = ($this->input)(1, Sudut::Merah, JenisSerangan::Pukulan, $t0->clone()->addMilliseconds(5));

    $hasilA = $this->evaluator->evaluasi($a);
    $hasilB = $this->evaluator->evaluasi($b);

    expect(collect([$hasilA, $hasilB])->filter())->toHaveCount(1)
        ->and(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(1);
});
