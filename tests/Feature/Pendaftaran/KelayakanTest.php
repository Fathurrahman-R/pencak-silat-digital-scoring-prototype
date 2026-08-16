<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Support\Pendaftaran\PeriksaKelayakan;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->periksa = new PeriksaKelayakan;
});

/** Kelas dewasa putra 55–60 kg. */
function kelasDewasaC(Tournament $tournament)
{
    return $tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)
        ->where('code', 'C')
        ->firstOrFail();
}

function atletDewasa(Contingent $kontingen, array $ganti = []): Athlete
{
    return Athlete::factory()
        ->for($kontingen)
        ->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(array_merge(['weight_claim' => 58.0], $ganti));
}

it('menerima atlet yang cocok gender, golongan usia, dan berat', function () {
    $hasil = $this->periksa->untukKelasTanding(
        kelasDewasaC($this->tournament),
        [atletDewasa($this->kontingen)],
    );

    expect($hasil->diterima())->toBeTrue();
});

it('menolak atlet yang jenis kelaminnya tidak sesuai nomor', function () {
    $putri = Athlete::factory()->for($this->kontingen)->putri()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);

    $hasil = $this->periksa->untukKelasTanding(kelasDewasaC($this->tournament), [$putri]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('tidak sesuai nomor Putra');
});

/*
 * Umur dihitung pada bulan kejuaraan dimulai, bukan pada saat mendaftar
 * (Pasal 2). Anak yang berulang tahun di antara keduanya berpindah golongan,
 * dan yang berlaku adalah golongannya saat bertanding.
 */
it('memakai umur pada tanggal kejuaraan, bukan umur saat mendaftar', function () {
    // Berumur 17 tahun pada hari ini, tetapi sudah 18 saat kejuaraan dimulai.
    $remaja = Athlete::factory()->for($this->kontingen)->putra()
        ->create(['birth_date' => '2008-08-01', 'weight_claim' => 58.0]);

    $kelasRemaja = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Remaja, JenisKelamin::Putra)->where('code', 'E')->firstOrFail();

    $hasil = $this->periksa->untukKelasTanding($kelasRemaja, [$remaja]);

    expect($remaja->umurSaatKejuaraan($this->tournament))->toBe(18)
        ->and($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('golongan Dewasa');
});

it('menolak berat klaim di luar rentang kelas', function () {
    $berat = atletDewasa($this->kontingen, ['weight_claim' => 72.0]);

    $hasil = $this->periksa->untukKelasTanding(kelasDewasaC($this->tournament), [$berat]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('di luar Kelas C');
});

it('menolak atlet yang sudah terdaftar di kelas yang sama', function () {
    $pesilat = atletDewasa($this->kontingen);
    $kelas = kelasDewasaC($this->tournament);

    $lama = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $kelas->id]);
    $lama->athletes()->attach($pesilat);

    $hasil = $this->periksa->untukKelasTanding($kelas, [$pesilat]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('sudah terdaftar');
});

it('mengizinkan pendaftaran yang sedang disunting melewati pemeriksaan dirinya sendiri', function () {
    $pesilat = atletDewasa($this->kontingen);
    $kelas = kelasDewasaC($this->tournament);

    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $kelas->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $hasil = $this->periksa->untukKelasTanding($kelas, [$pesilat], kecualikanPendaftaran: $pendaftaran->id);

    expect($hasil->diterima())->toBeTrue();
});

it('menolak kategori tanding yang diisi lebih dari satu pesilat', function () {
    $hasil = $this->periksa->untukKelasTanding(kelasDewasaC($this->tournament), [
        atletDewasa($this->kontingen),
        atletDewasa($this->kontingen),
    ]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('tepat satu pesilat');
});

/*
 * Jumlah pesilat per nomor jurus bukan sekadar kelengkapan data: nomor beregu
 * ditagih per tim, dan tim yang kurang orang tidak bisa tampil.
 */
it('menuntut jumlah pesilat sesuai nomor jurus', function (JenisJurus $jenis, int $diisi, bool $lolos) {
    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', $jenis)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $atlet = collect(range(1, $diisi))->map(fn () => atletDewasa($this->kontingen));

    expect($this->periksa->untukNomorJurus($nomor, $atlet)->diterima())->toBe($lolos);
})->with([
    'tunggal diisi satu' => [JenisJurus::Tunggal, 1, true],
    'ganda diisi dua' => [JenisJurus::Ganda, 2, true],
    'regu diisi tiga' => [JenisJurus::Regu, 3, true],
    'ganda diisi satu' => [JenisJurus::Ganda, 1, false],
    'regu diisi dua' => [JenisJurus::Regu, 2, false],
    'tunggal diisi dua' => [JenisJurus::Tunggal, 2, false],
]);

it('menolak nomor beregu yang pesilatnya lintas kontingen', function () {
    $lain = Contingent::factory()->for($this->tournament)->create();

    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Ganda)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $hasil = $this->periksa->untukNomorJurus($nomor, [
        atletDewasa($this->kontingen),
        atletDewasa($lain),
    ]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('kontingen yang sama');
});

it('menolak satu pesilat yang mengisi dua tempat pada nomor yang sama', function () {
    $pesilat = atletDewasa($this->kontingen);

    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Ganda)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $hasil = $this->periksa->untukNomorJurus($nomor, [$pesilat, $pesilat]);

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('dua tempat');
});

it('menolak atlet dari kejuaraan lain', function () {
    $kejuaraanLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    $kontingenLain = Contingent::factory()->for($kejuaraanLain)->create();

    $hasil = $this->periksa->untukKelasTanding(
        kelasDewasaC($this->tournament),
        [atletDewasa($kontingenLain)],
    );

    expect($hasil->diterima())->toBeFalse()
        ->and($hasil->pesan())->toContain('kejuaraan lain');
});

/*
 * Mengumpulkan semua pelanggaran sekaligus, bukan berhenti di yang pertama.
 * Official yang mendaftarkan puluhan atlet perlu melihat semuanya dalam satu
 * kali lihat.
 */
it('mengumpulkan seluruh alasan penolakan sekaligus', function () {
    $salahSemua = Athlete::factory()->for($this->kontingen)->putri()
        ->golongan(GolonganUsia::Remaja, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 95.0]);

    $hasil = $this->periksa->untukKelasTanding(kelasDewasaC($this->tournament), [$salahSemua]);

    expect($hasil->alasan)->toHaveCount(3);
});

it('menawarkan hanya kelas yang cocok untuk seorang atlet', function () {
    $pesilat = atletDewasa($this->kontingen, ['weight_claim' => 58.0]);

    $cocok = $this->periksa->kelasYangCocok($pesilat, $this->kontingen);

    expect($cocok)->toHaveCount(1)
        ->and($cocok->first()->code)->toBe('C');
});

/*
 * Usia Dini 1 bertanding tanpa pembagian kelas berat (Pasal 3.2.a), jadi tidak
 * ada kelas yang bisa ditawarkan — dan itu bukan kesalahan.
 */
it('tidak menawarkan kelas untuk golongan yang tidak memakai kelas berat', function () {
    $anak = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::UsiaDini1, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 22.0]);

    expect($this->periksa->kelasYangCocok($anak, $this->kontingen))->toBeEmpty();
});
