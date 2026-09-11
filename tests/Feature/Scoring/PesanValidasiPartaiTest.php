<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/**
 * Pesan yang dibaca juri dan wasit di gelanggang, bukan pesan untuk developer.
 *
 * Seluruh aplikasi berbahasa Indonesia, tapi sebagian galat masih memakai
 * nama field mentah dalam bahasa Inggris: "The selected corner is invalid."
 * muncul berdampingan dengan "babak harus bernilai minimal 1." dalam satu
 * respons yang sama.
 */
beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $buat = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->operator = $buat('operator-it');
    $this->juri = $buat('juri');
    $this->wasit = $buat('wasit');

    $gelanggang = Arena::factory()->for($this->tournament)->create();
    $gelanggang->operators()->attach($this->operator);
    $this->match->update(['arena_id' => $gelanggang->id, 'order_in_arena' => 1]);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->wasit->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->operator)->post(
        route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]),
        ['babak' => 1],
    );

    $this->kirimNilai = fn (array $muatan) => $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), $muatan);
});

it('menyebut sudut dalam bahasa Indonesia saat nilainya tidak dikenal', function () {
    ($this->kirimNilai)(['babak' => 1, 'corner' => 'hijau', 'jenis' => 'pukulan'])
        ->assertStatus(422)
        ->assertJsonPath('errors.corner.0', 'Sudut yang dipilih tidak dikenal.');
});

it('menyebut jenis serangan dalam bahasa Indonesia saat nilainya tidak dikenal', function () {
    ($this->kirimNilai)(['babak' => 1, 'corner' => 'red', 'jenis' => 'kuncian'])
        ->assertStatus(422)
        ->assertJsonPath('errors.jenis.0', 'Jenis serangan yang dipilih tidak dikenal.');
});

it('tidak menyisakan satu pun pesan berbahasa Inggris dalam satu respons', function () {
    $respons = ($this->kirimNilai)(['babak' => 0, 'corner' => 'hijau', 'jenis' => 'kuncian'])
        ->assertStatus(422);

    $semua = collect($respons->json('errors'))->flatten()->implode(' ');

    expect($semua)->not->toContain('The ')
        ->and($semua)->not->toContain('selected')
        ->and($semua)->not->toContain('is invalid');
});

/*
 * Babak di luar jangkauan ditolak seperti babak 0 dan babak -1: sama-sama
 * angka yang tidak mungkin ada, jadi jawabannya juga harus sama. Sebelumnya
 * babak 99 dijawab 200 dengan peringatan, sehingga klien yang memeriksa kode
 * status membacanya sebagai berhasil.
 */
it('menolak babak yang melampaui jumlah babak partai', function () {
    ($this->kirimNilai)(['babak' => 99, 'corner' => 'red', 'jenis' => 'pukulan'])
        ->assertStatus(422)
        ->assertJsonPath('errors.babak.0', 'Partai ini hanya punya 3 babak.');

    expect($this->match->judgeInputs()->count())->toBe(0);
});

it('tetap menerima babak terakhir yang sah', function () {
    ($this->kirimNilai)(['babak' => 3, 'corner' => 'red', 'jenis' => 'pukulan'])
        ->assertOk();
});
