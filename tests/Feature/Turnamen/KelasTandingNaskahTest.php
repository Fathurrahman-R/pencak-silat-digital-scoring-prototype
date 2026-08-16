<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Tournament;
use App\Support\Peraturan\KelasTandingNaskah;
use App\Support\Peraturan\NomorJurusNaskah;

/*
 * Jumlah nomor disebut naskah terang-terangan di tiap pasal ("Jumlah 20 nomor
 * dengan berat badan untuk Putra"). Angka itu dipakai sebagai penjaga: kalau
 * salah satu deret kelas meleset satu langkah, jumlahnya ikut berubah dan
 * test ini gagal sebelum tabelnya sempat dipakai kejuaraan sungguhan.
 */
it('menghasilkan jumlah kelas persis seperti disebut naskah', function (
    GolonganUsia $golongan,
    JenisKelamin $jenisKelamin,
    int $jumlah,
) {
    expect(KelasTandingNaskah::untuk($golongan, $jenisKelamin))->toHaveCount($jumlah);
})->with([
    // Pasal 3 ayat 3: 20 nomor untuk masing-masing gender.
    'usia dini 2 putra' => [GolonganUsia::UsiaDini2, JenisKelamin::Putra, 20],
    'usia dini 2 putri' => [GolonganUsia::UsiaDini2, JenisKelamin::Putri, 20],

    // Pasal 4: 17 nomor untuk masing-masing gender.
    'pra remaja putra' => [GolonganUsia::PraRemaja, JenisKelamin::Putra, 17],
    'pra remaja putri' => [GolonganUsia::PraRemaja, JenisKelamin::Putri, 17],

    // Pasal 5: putra 15, putri 13.
    'remaja putra' => [GolonganUsia::Remaja, JenisKelamin::Putra, 15],
    'remaja putri' => [GolonganUsia::Remaja, JenisKelamin::Putri, 13],

    // Pasal 6 dan 7: putra 13, putri 11. Master menyalin tabel Dewasa.
    'dewasa putra' => [GolonganUsia::Dewasa, JenisKelamin::Putra, 13],
    'dewasa putri' => [GolonganUsia::Dewasa, JenisKelamin::Putri, 11],
    'master 1 putra' => [GolonganUsia::Master1, JenisKelamin::Putra, 13],
    'master 2 putri' => [GolonganUsia::Master2, JenisKelamin::Putri, 11],
]);

it('tidak memberi kelas berat pada golongan yang tidak memakainya', function () {
    expect(KelasTandingNaskah::untuk(GolonganUsia::PraUsiaDini, JenisKelamin::Putra))->toBeEmpty()
        ->and(KelasTandingNaskah::untuk(GolonganUsia::UsiaDini1, JenisKelamin::Putra))->toBeEmpty();
});

it('menyalin tabel dewasa apa adanya untuk kedua golongan master', function () {
    foreach (JenisKelamin::cases() as $jenisKelamin) {
        $dewasa = KelasTandingNaskah::untuk(GolonganUsia::Dewasa, $jenisKelamin);

        expect(KelasTandingNaskah::untuk(GolonganUsia::Master1, $jenisKelamin))->toBe($dewasa)
            ->and(KelasTandingNaskah::untuk(GolonganUsia::Master2, $jenisKelamin))->toBe($dewasa);
    }
});

/*
 * Ujung tiap tangga adalah tempat kesalahan paling mungkin bersembunyi, dan
 * sekaligus tempat yang paling mahal kalau salah: kelas terbuka menampung
 * atlet terberat, dan batas bawahnya menentukan siapa yang boleh naik ke sana.
 */
it('menutup tangga kelas dengan angka yang benar', function (
    GolonganUsia $golongan,
    JenisKelamin $jenisKelamin,
    string $kodeTerakhir,
    ?float $minTerakhir,
    ?float $maxTerakhir,
) {
    $terakhir = collect(KelasTandingNaskah::untuk($golongan, $jenisKelamin))->last();

    expect($terakhir['code'])->toBe($kodeTerakhir)
        ->and($terakhir['weight_min'])->toBe($minTerakhir)
        ->and($terakhir['weight_max'])->toBe($maxTerakhir);
})->with([
    'usia dini 2' => [GolonganUsia::UsiaDini2, JenisKelamin::Putra, 'Open', 64.0, 68.0],
    'pra remaja' => [GolonganUsia::PraRemaja, JenisKelamin::Putra, 'Open', 78.0, 84.0],
    'remaja putra' => [GolonganUsia::Remaja, JenisKelamin::Putra, 'Open 2', 100.0, null],
    'remaja putri' => [GolonganUsia::Remaja, JenisKelamin::Putri, 'Open 2', 92.0, null],
    'dewasa putra' => [GolonganUsia::Dewasa, JenisKelamin::Putra, 'Open 2', 110.0, null],
    'dewasa putri' => [GolonganUsia::Dewasa, JenisKelamin::Putri, 'Open 2', 100.0, null],
]);

/*
 * Syarat yang membuat timbang badan bisa berjalan tanpa penilaian manusia:
 * berapa pun beratnya, atlet jatuh ke tepat satu kelas — tidak nol, tidak dua.
 *
 * Ini menguji tepat di angka batas, karena di situlah rumusan naskah yang
 * berbeda-beda ("Diatas ... sampai", "... sampai ...", "Dibawah ...") bisa
 * saling tumpang tindih atau meninggalkan celah.
 */
it('menempatkan berapa pun berat badan ke tepat satu kelas', function (
    GolonganUsia $golongan,
    JenisKelamin $jenisKelamin,
) {
    $tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($tournament, [$golongan]);

    $kelas = $tournament->weightClasses()
        ->untuk($golongan, $jenisKelamin)
        ->get();

    // Uji tepat di angka batas dan setengah kilo di kedua sisinya.
    $uji = $kelas
        ->flatMap(fn ($k) => array_filter([$k->weight_min, $k->weight_max]))
        ->flatMap(fn ($batas) => [(float) $batas - 0.5, (float) $batas, (float) $batas + 0.5])
        ->unique();

    /*
     * Di luar tangga memang tidak ada kelas, dan itu benar. Golongan muda
     * menutup tangganya di kedua ujung — Usia Dini 2 berhenti di 68 kg — jadi
     * atlet di luar rentang itu tidak dipertandingkan pada golongannya sama
     * sekali, bukan dimasukkan paksa ke kelas terdekat.
     */
    $terendah = (float) $kelas->min('weight_min');
    $tertinggi = $kelas->contains(fn ($k): bool => $k->weight_max === null)
        ? INF
        : (float) $kelas->max('weight_max');

    $dalamTangga = $uji->filter(fn (float $kg): bool => $kg >= $terendah && $kg <= $tertinggi);

    foreach ($dalamTangga as $kg) {
        $cocok = $kelas->filter(fn ($k): bool => $k->memuatBerat($kg));

        expect($cocok)->toHaveCount(1, "Berat {$kg} kg cocok ke {$cocok->count()} kelas, seharusnya 1.");
    }
})->with([
    'usia dini 2 putra' => [GolonganUsia::UsiaDini2, JenisKelamin::Putra],
    'pra remaja putri' => [GolonganUsia::PraRemaja, JenisKelamin::Putri],
    'remaja putra' => [GolonganUsia::Remaja, JenisKelamin::Putra],
    'remaja putri' => [GolonganUsia::Remaja, JenisKelamin::Putri],
    'dewasa putra' => [GolonganUsia::Dewasa, JenisKelamin::Putra],
    'dewasa putri' => [GolonganUsia::Dewasa, JenisKelamin::Putri],
]);

/*
 * Golongan muda menutup tangganya di kedua ujung, sedangkan Remaja ke atas
 * membiarkan kelas terberatnya terbuka. Perbedaan ini menentukan apakah
 * seorang atlet punya kelas sama sekali, jadi dikunci terpisah.
 */
it('menutup tangga golongan muda dan membuka tangga golongan dewasa', function () {
    $tertutup = collect(KelasTandingNaskah::untuk(GolonganUsia::UsiaDini2, JenisKelamin::Putra));
    $terbuka = collect(KelasTandingNaskah::untuk(GolonganUsia::Dewasa, JenisKelamin::Putra));

    expect($tertutup->last()['weight_max'])->toBe(68.0)
        ->and($terbuka->last()['weight_max'])->toBeNull();
});

/*
 * Kelas terendah Usia Dini 2 ditulis "26 kg sampai 28 kg" — batas bawahnya
 * inklusif. Kalau diperlakukan eksklusif seperti kelas lainnya, atlet 26,0 kg
 * ditolak seluruh kelas dan tidak bisa bertanding sama sekali.
 */
it('menerima atlet yang beratnya persis di batas bawah kelas terendah', function () {
    $tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($tournament, [GolonganUsia::UsiaDini2]);

    $kelasA = $tournament->weightClasses()->where('code', 'A')->first();

    expect($kelasA->memuatBerat(26.0))->toBeTrue()
        ->and($kelasA->memuatBerat(25.9))->toBeFalse();
});

/*
 * Kelas "Dibawah 39 kg" pada Remaja bersebelahan dengan kelas A "39 kg sampai
 * 43 kg". Batas atas kelas bawah harus eksklusif, kalau tidak atlet 39,0 kg
 * memenuhi syarat dua kelas sekaligus.
 */
it('memisahkan kelas di bawah ambang dari kelas terendah bernomor', function () {
    $tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($tournament, [GolonganUsia::Remaja]);

    $bawah = $tournament->weightClasses()->where('code', '<39')->first();
    $kelasA = $tournament->weightClasses()->where('code', 'A')->first();

    expect($bawah->memuatBerat(38.9))->toBeTrue()
        ->and($bawah->memuatBerat(39.0))->toBeFalse()
        ->and($kelasA->memuatBerat(39.0))->toBeTrue();
});

it('memberi nomor jurus sesuai golongan usia', function () {
    expect(NomorJurusNaskah::untuk(GolonganUsia::PraUsiaDini))
        ->toBe([JenisJurus::TunggalBebas])
        ->and(NomorJurusNaskah::untuk(GolonganUsia::UsiaDini2))
        ->toContain(JenisJurus::ReguA)
        ->not->toContain(JenisJurus::ReguB)
        ->and(NomorJurusNaskah::untuk(GolonganUsia::PraRemaja))
        ->toContain(JenisJurus::ReguB)
        ->not->toContain(JenisJurus::ReguA)
        ->and(NomorJurusNaskah::untuk(GolonganUsia::Dewasa))
        ->toBe([JenisJurus::Tunggal, JenisJurus::TunggalBebas, JenisJurus::Ganda, JenisJurus::Regu]);
});

it('memberi waktu penampilan tetap pada nomor golongan muda', function () {
    $tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($tournament, [GolonganUsia::UsiaDini2]);

    $tanganKosong = $tournament->jurusEvents()
        ->where('jenis', JenisJurus::TunggalTanganKosong)->first();
    $senjata = $tournament->jurusEvents()
        ->where('jenis', JenisJurus::TunggalSenjata)->first();

    // Pasal 10 ayat 3: tangan kosong 1 menit 30 detik, bersenjata 1 menit 50.
    expect($tanganKosong->waktu_acuan_ms)->toBe(90_000)
        ->and($senjata->waktu_acuan_ms)->toBe(110_000);
});

it('membiarkan waktu nomor tunggal dewasa ditentukan per tahap', function () {
    $tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($tournament, [GolonganUsia::Dewasa]);

    $tunggal = $tournament->jurusEvents()->where('jenis', JenisJurus::Tunggal)->first();

    expect($tunggal->waktu_acuan_ms)->toBeNull()
        ->and($tunggal->waktuAcuanMs('penyisihan'))->toBe(80_000)
        ->and($tunggal->waktuAcuanMs('final'))->toBe(180_000);
});

it('menyusun seluruh master data satu kejuaraan tanpa duplikat saat diulang', function () {
    $tournament = Tournament::factory()->create();
    $susun = new SusunMasterDataTurnamen;

    $susun($tournament);
    $jumlahKelas = $tournament->weightClasses()->count();
    $jumlahNomor = $tournament->jurusEvents()->count();

    $susun($tournament);

    expect($tournament->weightClasses()->count())->toBe($jumlahKelas)
        ->and($tournament->jurusEvents()->count())->toBe($jumlahNomor)
        ->and($tournament->ruleSetting()->count())->toBe(1);
});

/*
 * Panitia yang sudah mematikan sebuah kelas tidak boleh menemukannya hidup
 * lagi hanya karena master data disusun ulang.
 */
it('tidak menghidupkan kembali kelas yang sudah dimatikan panitia', function () {
    $tournament = Tournament::factory()->create();
    $susun = new SusunMasterDataTurnamen;

    $susun($tournament, [GolonganUsia::Dewasa]);
    $tournament->weightClasses()->where('code', 'Open 2')->update(['is_active' => false]);

    $susun($tournament, [GolonganUsia::Dewasa]);

    expect($tournament->weightClasses()->where('code', 'Open 2')->first()->is_active)->toBeFalse();
});
