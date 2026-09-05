<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\AkibatProtes;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\ManagerProtest;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\MatchTimer;
use App\Support\Var\KeputusanProtesManajer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Pasal 15 ayat 4 huruf c.e memberi TIGA bentuk jawaban atas protes yang
 * diterima, dan sampai sekarang sistem hanya mencatat "diterima" tanpa
 * menyebut yang mana. Protes yang diterima tanpa akibat adalah keputusan yang
 * tidak bisa dijalankan siapa pun: panitia tahu protesnya benar, tapi tidak
 * tahu apa yang harus terjadi berikutnya di gelanggang.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->ketua = User::factory()->create();
    $this->ketua->syncRoles(['ketua-pertandingan']);

    $this->putuskan = new KeputusanProtesManajer;

    // Tenggatnya wajib -- lihat migrasi manager_protests. Angkanya mengikuti
    // Pasal 15 ayat 4: 20 menit mengembalikan formulir, 2 jam memutuskan.
    $this->protes = fn () => ManagerProtest::create([
        'match_id' => $this->match->id,
        'level' => 'pertama',
        'diajukan_at' => now(),
        'tenggat_formulir_at' => now()->addMinutes(20),
        'tenggat_keputusan_at' => now()->addHours(2),
    ]);
});

it('menolak menerima protes tanpa menyebut akibatnya', function () {
    expect(fn () => ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua))
        ->toThrow(RuntimeException::class, 'harus menyebut akibatnya');
});

it('mencatat akibat pada protes yang diterima', function () {
    $protes = ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua, AkibatProtes::BabakTambahan);

    expect($protes->fresh())
        ->keputusan->toBe('diterima')
        ->akibat->toBe(AkibatProtes::BabakTambahan)
        ->akibat_diterapkan_at->toBeNull();
});

/** Protes yang DITOLAK tidak punya akibat -- tidak ada yang harus dijalankan. */
it('tidak mencatat akibat pada protes yang ditolak', function () {
    $protes = ($this->putuskan)(($this->protes)(), 'ditolak', 'tidak terbukti', $this->ketua, AkibatProtes::BabakTambahan);

    expect($protes->fresh()->akibat)->toBeNull();
});

/*
 * Babak melebihi jumlah golongan usia hanya dibuka lewat satu jalan: protes
 * diterima dengan akibat babak tambahan. Melonggarkan setelan peraturan akan
 * membukanya untuk SELURUH partai, termasuk yang tidak pernah diprotes.
 */
it('menolak babak melebihi jumlah golongan usia tanpa protes', function () {
    $timer = new MatchTimer;

    for ($babak = 1; $babak <= 3; $babak++) {
        $timer->mulaiBabak($this->match->fresh(), $babak);
        $timer->selesaikanBabak($this->match->fresh()->babakAktif());
    }

    expect(fn () => $timer->mulaiBabak($this->match->fresh(), 4))
        ->toThrow(RuntimeException::class, 'hanya punya 3 babak');
});

it('membuka satu babak tambahan setelah protes diterima', function () {
    ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua, AkibatProtes::BabakTambahan);

    $timer = new MatchTimer;

    for ($babak = 1; $babak <= 3; $babak++) {
        $timer->mulaiBabak($this->match->fresh(), $babak);
        $timer->selesaikanBabak($this->match->fresh()->babakAktif());
    }

    $babakTambahan = $timer->mulaiBabak($this->match->fresh(), 4);

    expect($babakTambahan->round)->toBe(4)
        ->and($this->match->fresh()->current_round)->toBe(4);
});

/** Batasnya tetap tegas: SATU babak, bukan berapa pun. */
it('tetap menolak babak kedua di luar jumlah golongan usia', function () {
    ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua, AkibatProtes::BabakTambahan);

    $timer = new MatchTimer;

    for ($babak = 1; $babak <= 4; $babak++) {
        $timer->mulaiBabak($this->match->fresh(), $babak);
        $timer->selesaikanBabak($this->match->fresh()->babakAktif());
    }

    expect(fn () => $timer->mulaiBabak($this->match->fresh(), 5))
        ->toThrow(RuntimeException::class, 'hanya punya 3 babak');
});

/*
 * Tanpa ini pemenang naik ke slot bagan berikutnya SEBELUM babak tambahannya
 * dimainkan. Bagan yang telanjur bergeser tidak bisa ditarik kembali tanpa
 * membatalkan partai-partai sesudahnya.
 */
it('menahan pengesahan selama akibat protes belum dijalankan', function () {
    ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua, AkibatProtes::BabakTambahan);

    $this->match->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $this->match->red_registration_id,
        'win_reason' => 'angka',
    ]);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.sahkan', [$this->tournament, $this->match]))
        ->assertSessionHasErrors('match');

    expect($this->match->fresh()->disahkan())->toBeFalse();
});

it('mengizinkan pengesahan setelah akibatnya ditandai dijalankan', function () {
    $protes = ($this->putuskan)(($this->protes)(), 'diterima', null, $this->ketua, AkibatProtes::BabakTambahan);
    $protes->update(['akibat_diterapkan_at' => now()]);

    $this->match->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $this->match->red_registration_id,
        'win_reason' => 'angka',
    ]);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.sahkan', [$this->tournament, $this->match]))
        ->assertSessionHasNoErrors();

    expect($this->match->fresh()->disahkan())->toBeTrue();
});

it('memisahkan akibat yang berlaku untuk Tanding dan Jurus', function () {
    expect(AkibatProtes::untukTanding())->toBe([AkibatProtes::UbahHasil, AkibatProtes::BabakTambahan])
        ->and(AkibatProtes::untukJurus())->toBe([AkibatProtes::UbahHasil, AkibatProtes::PenampilanUlang]);
});
