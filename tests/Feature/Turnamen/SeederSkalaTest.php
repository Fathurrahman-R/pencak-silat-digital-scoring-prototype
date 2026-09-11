<?php

use App\Enums\ModeBagan;
use App\Enums\StatusPendaftaran;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\JurusEvent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\WeightClass;
use Database\Seeders\KejuaraanSkalaSeeder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Data uji berskala penuh.
 *
 * Yang dijaga di sini bukan angka persisnya -- profilnya boleh diubah kapan
 * saja -- melainkan sifat yang membuat data ini berguna: TIDAK ADA kelas
 * tanding maupun nomor Jurus yang kosong. Satu kelas yang terlewat berarti
 * satu halaman yang tidak pernah diuji berisi, dan yang paling sering
 * terlewat justru golongan usia dini, tempat kelasnya paling banyak.
 *
 * Dijalankan pada skala SEDANG saja. Skala besar memakai jalur kode yang sama
 * persis dengan angka yang lebih besar; menjalankannya di sini menambah
 * semenit lebih ke tiap rangkaian uji tanpa menambah satu pun cabang yang
 * diperiksa.
 */

beforeEach(function () {
    /*
     * Peran dan resource key diseed di sini, bukan diserahkan ke callOnce()
     * di dalam seedernya: callOnce mengingat apa yang sudah dipanggil untuk
     * SELURUH proses, sementara tiap uji memulai basis data dari nol. Uji
     * kedua dan seterusnya karena itu akan berjalan tanpa satu pun peran, dan
     * gagal pada baris yang tidak ada hubungannya dengan yang diuji.
     */
    $seeder = app(KejuaraanSkalaSeeder::class);
    $seeder->skala('sedang')->run();

    $this->tournament = Tournament::where('slug', KejuaraanSkalaSeeder::SLUG_SEDANG)->firstOrFail();
});

it('mengisi seluruh kelas tanding dengan peserta sah', function () {
    $kelas = WeightClass::where('tournament_id', $this->tournament->id)->pluck('id');

    $berpeserta = Registration::whereIn('weight_class_id', $kelas)
        ->where('status', StatusPendaftaran::Terverifikasi)
        ->select('weight_class_id')
        ->groupBy('weight_class_id')
        ->pluck('weight_class_id');

    expect($kelas)->not->toBeEmpty()
        ->and($berpeserta->count())->toBe($kelas->count());
});

it('mengisi seluruh nomor jurus, termasuk ganda dan regu', function () {
    $nomor = JurusEvent::where('tournament_id', $this->tournament->id)->get();

    $berpeserta = Registration::whereIn('jurus_event_id', $nomor->pluck('id'))
        ->where('status', StatusPendaftaran::Terverifikasi)
        ->select('jurus_event_id')
        ->groupBy('jurus_event_id')
        ->pluck('jurus_event_id');

    // Pendaftaran nomor ganda/regu membawa lebih dari satu pesilat; kalau
    // pesilat tambahannya tidak ikut dibuat, pendaftarannya ditolak
    // PeriksaKelayakan dan nomornya berakhir kosong.
    $gandaAtauRegu = $nomor->filter(fn (JurusEvent $j) => $j->jenis->jumlahPesilat() > 1);

    expect($berpeserta->count())->toBe($nomor->count())
        ->and($gandaAtauRegu)->not->toBeEmpty();
});

it('memakai kedua mode bagan di satu kejuaraan', function () {
    $mode = Bracket::whereHas('weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))
        ->get()
        ->groupBy(fn (Bracket $b) => $b->mode->value);

    expect($mode->has(ModeBagan::Gugur->value))->toBeTrue()
        ->and($mode->has(ModeBagan::Pemasalan->value))->toBeTrue();
});

it('menjadwalkan partai siap ke seluruh gelanggang beserta aparatnya', function () {
    $gelanggang = Arena::where('tournament_id', $this->tournament->id)->get();

    $terjadwal = SilatMatch::whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))
        ->whereNotNull('arena_id')
        ->get();

    $tanpaAparat = $terjadwal->filter(
        fn (SilatMatch $p) => $p->officials()->where('role', MatchOfficial::ROLE_WASIT)->doesntExist(),
    );

    expect($gelanggang)->toHaveCount(2)
        ->and($terjadwal)->not->toBeEmpty()
        ->and($terjadwal->pluck('arena_id')->unique()->count())->toBe(2)
        ->and($tanpaAparat)->toBeEmpty();
});

/*
 * Gelanggang tanpa Pengendali tidak bisa memulai satu babak pun: panel
 * kendalinya membalas 403, dan data uji yang tidak bisa dijalankan tidak
 * menguji apa pun. Penugasannya berdiri di tabel yang BERBEDA dari operator,
 * dan itulah yang terlewat saat seeder ini pertama ditulis.
 */
it('memberi tiap gelanggang seorang pengendali, bukan cuma operator', function () {
    $gelanggang = Arena::where('tournament_id', $this->tournament->id)->get();

    foreach ($gelanggang as $satu) {
        expect($satu->pengendali()->count())->toBeGreaterThan(0)
            ->and($satu->operators()->count())->toBeGreaterThan(0);

        expect($satu->pengendali()->first()->hasRole('pengendali-gelanggang'))->toBeTrue();
    }
});

/*
 * Saklar --tanpa-bagan menyiapkan data untuk melatih penyusunan bagannya
 * sendiri: pendaftaran sudah sah dan lunas, tapi belum satu pun bagan berdiri.
 */
it('berhenti sebelum bagan saat diminta', function () {
    Tournament::where('slug', KejuaraanSkalaSeeder::SLUG_SEDANG)->firstOrFail()->forceDelete();

    app(KejuaraanSkalaSeeder::class)->skala('sedang')->tanpaBagan()->run();

    $tournament = Tournament::where('slug', KejuaraanSkalaSeeder::SLUG_SEDANG)->firstOrFail();

    $sah = Registration::whereHas('contingent', fn ($q) => $q->where('tournament_id', $tournament->id))
        ->where('status', StatusPendaftaran::Terverifikasi)
        ->count();

    $bagan = Bracket::whereHas('weightClass', fn ($q) => $q->where('tournament_id', $tournament->id))->count();

    expect($sah)->toBeGreaterThan(0)
        ->and($bagan)->toBe(0);
});

it('menolak skala yang tidak dikenal', function () {
    $this->artisan('silat:simulasi', ['--skala' => 'raksasa'])
        ->expectsOutputToContain('Skala tidak dikenal')
        ->assertFailed();
});

it('menolak --tanpa-bagan pada skala kecil', function () {
    $this->artisan('silat:simulasi', ['--skala' => 'kecil', '--tanpa-bagan' => true])
        ->expectsOutputToContain('hanya berlaku untuk skala sedang dan besar')
        ->assertFailed();
});
