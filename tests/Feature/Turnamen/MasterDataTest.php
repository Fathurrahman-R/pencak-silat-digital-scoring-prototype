<?php

use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\JurusEvent;
use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use App\Models\WeightClass;

it('menyalin parameter peraturan dari naskah saat setelan dibuat', function () {
    $setelan = TournamentRuleSetting::factory()->create();

    expect($setelan->jumlah_juri_tanding)->toBe(config('scoring.juri.tanding.jumlah'))
        ->and($setelan->ambang_sepakat)->toBe(config('scoring.juri.tanding.ambang_sepakat'))
        ->and($setelan->window_konsensus_ms)->toBe(config('scoring.juri.tanding.window_ms'))
        ->and($setelan->nilai)->toBe(config('scoring.tanding.nilai'));
});

/*
 * Inti dari pemisahan setelan per kejuaraan: begitu tersimpan, baris itulah
 * yang berlaku. Menyunting berkas konfigurasi tidak boleh menyentuh kejuaraan
 * yang sedang berjalan — kalau bisa, dasar perhitungan partai yang sudah
 * dinilai ikut berubah di belakang layar.
 */
it('tidak ikut berubah saat berkas konfigurasi disunting', function () {
    $setelan = TournamentRuleSetting::factory()->create();

    config(['scoring.juri.tanding.ambang_sepakat' => 3]);
    $setelan->refresh();

    expect($setelan->ambang_sepakat)->toBe(2);
});

it('menolak ambang sepakat yang melebihi jumlah juri', function () {
    $setelan = TournamentRuleSetting::factory()->create([
        'jumlah_juri_tanding' => 3,
        'ambang_sepakat' => 4,
    ]);

    expect($setelan->ambangMasukAkal())->toBeFalse();
});

it('memberi jumlah dan durasi babak sesuai golongan usia', function () {
    $setelan = TournamentRuleSetting::factory()->create();

    expect($setelan->babakUntuk(GolonganUsia::Dewasa))
        ->toBe(['jumlah' => 3, 'durasi_ms' => 120_000])
        ->and($setelan->babakUntuk(GolonganUsia::Master2))
        ->toBe(['jumlah' => 2, 'durasi_ms' => 60_000]);
});

it('memakai ambang WMP khusus untuk golongan usia dini', function () {
    $setelan = TournamentRuleSetting::factory()->create();

    expect($setelan->wmpUntuk(GolonganUsia::UsiaDini2))
        ->toBe(['selisih' => 20, 'mulai_babak' => 1])
        ->and($setelan->wmpUntuk(GolonganUsia::Dewasa))
        ->toBe(['selisih' => 30, 'mulai_babak' => 2]);
});

/*
 * Batas bawah eksklusif, batas atas inklusif. Kelas yang bersebelahan berbagi
 * angka batas, jadi kalau kedua ujungnya inklusif, satu berat badan akan masuk
 * dua kelas sekaligus dan petugas timbang harus memilih sendiri.
 */
it('menempatkan satu berat badan ke tepat satu kelas', function () {
    $tournament = Tournament::factory()->create();

    $bawah = WeightClass::factory()->for($tournament)->create([
        'code' => 'A', 'weight_min' => 45, 'weight_max' => 50,
    ]);
    $atas = WeightClass::factory()->for($tournament)->create([
        'code' => 'B', 'weight_min' => 50, 'weight_max' => 55,
    ]);

    expect($bawah->memuatBerat(50.0))->toBeTrue()
        ->and($atas->memuatBerat(50.0))->toBeFalse()
        ->and($bawah->memuatBerat(50.1))->toBeFalse()
        ->and($atas->memuatBerat(50.1))->toBeTrue();
});

it('membiarkan kelas terendah dan tertinggi tanpa ujung', function () {
    $terendah = WeightClass::factory()->create(['weight_min' => null, 'weight_max' => 45]);
    $tertinggi = WeightClass::factory()->create(['weight_min' => 95, 'weight_max' => null]);

    expect($terendah->memuatBerat(30.0))->toBeTrue()
        ->and($tertinggi->memuatBerat(140.0))->toBeTrue();
});

it('mengambil waktu acuan naskah untuk nomor yang waktunya diatur', function () {
    $nomor = JurusEvent::factory()->create([
        'jenis' => JenisJurus::Tunggal,
        'waktu_acuan_ms' => null,
    ]);

    expect($nomor->waktuAcuanMs('penyisihan'))->toBe(80_000)
        ->and($nomor->waktuAcuanMs('final'))->toBe(180_000);
});

it('memakai waktu acuan panitia untuk nomor yang tidak diatur naskah', function () {
    $ganda = JurusEvent::factory()->create([
        'jenis' => JenisJurus::Ganda,
        'waktu_acuan_ms' => null,
    ]);

    expect($ganda->waktuAcuanMs())->toBeNull();

    $ganda->update(['waktu_acuan_ms' => 180_000]);

    expect($ganda->fresh()->waktuAcuanMs())->toBe(180_000);
});

it('menyusun nama nomor jurus dari jenis, gender, dan golongan usia', function () {
    $nomor = JurusEvent::factory()->create([
        'jenis' => JenisJurus::ReguA,
        'jenis_kelamin' => JenisKelamin::Putri,
        'golongan_usia' => GolonganUsia::Remaja,
    ]);

    expect($nomor->nama())->toBe('Jurus Regu A Putri Remaja');
});

it('menghapus seluruh data turunan saat turnamen dihapus permanen', function () {
    $tournament = Tournament::factory()->create();
    TournamentRuleSetting::factory()->for($tournament)->create();
    Arena::factory()->for($tournament)->create();
    WeightClass::factory()->for($tournament)->create();
    JurusEvent::factory()->for($tournament)->create();

    $tournament->forceDelete();

    expect(Arena::count())->toBe(0)
        ->and(WeightClass::count())->toBe(0)
        ->and(JurusEvent::count())->toBe(0)
        ->and(TournamentRuleSetting::count())->toBe(0);
});

it('menolak kode gelanggang kembar di satu turnamen', function () {
    $tournament = Tournament::factory()->create();
    Arena::factory()->for($tournament)->create(['code' => 'G1']);

    Arena::factory()->for($tournament)->create(['code' => 'G1']);
})->throws(Illuminate\Database\QueryException::class);

it('mengizinkan kode gelanggang sama di turnamen berbeda', function () {
    Arena::factory()->for(Tournament::factory())->create(['code' => 'G1']);
    Arena::factory()->for(Tournament::factory())->create(['code' => 'G1']);

    expect(Arena::where('code', 'G1')->count())->toBe(2);
});
