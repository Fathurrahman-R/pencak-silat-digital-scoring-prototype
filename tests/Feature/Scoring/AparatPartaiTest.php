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
 * Wasit dan juri hanya berwenang atas partai yang benar-benar ditugaskan
 * kepada mereka. Izin peran saja tidak cukup: dua gelanggang berjalan
 * bersamaan, dan aparat gelanggang sebelah tidak boleh ikut menilai atau
 * menghukum di sini.
 *
 * Aparat tingkat kejuaraan -- Dewan Wasit Juri, Ketua Pertandingan -- sengaja
 * TIDAK ikut aturan ini. Mereka memang berwenang lintas gelanggang.
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

    $buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };

    $this->operator = $buatUser('operator-it');
    $this->pengawas = $buatUser('pengawas-wasit-juri');
    $this->ketua = $buatUser('ketua-pertandingan');

    /*
     * Partai dimainkan di Gelanggang A, dan operator bawaan test ini memang
     * operatornya. Gelanggang B beserta operatornya baru dibuat oleh test
     * yang memerlukannya.
     */
    $this->gelanggangA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $this->gelanggangA->operators()->attach($this->operator);
    $this->match->update(['arena_id' => $this->gelanggangA->id, 'order_in_arena' => 1]);

    $this->siapkanGelanggang = function () use ($buatUser) {
        $gelanggangB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B']);
        $operatorB = $buatUser('operator-it');
        $gelanggangB->operators()->attach($operatorB);

        $this->gelanggangA->operator = $this->operator;
        $gelanggangB->operator = $operatorB;

        return [$this->gelanggangA, $gelanggangB];
    };

    $this->juriDitugaskan = $buatUser('juri');
    $this->juriGelanggangLain = $buatUser('juri');
    $this->wasitDitugaskan = $buatUser('wasit');
    $this->wasitGelanggangLain = $buatUser('wasit');

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->wasitDitugaskan->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);
    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->juriDitugaskan->id,
        'role' => MatchOfficial::ROLE_JURI,
        'number' => 1,
    ]);

    $this->mulaiBabak = function () {
        $this->actingAs($this->operator)->post(
            route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]),
            ['babak' => 1],
        );
    };
});

it('menolak nilai dari juri yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juriGelanggangLain)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])
        ->assertForbidden();

    expect($this->match->judgeInputs()->count())->toBe(0);
});

it('menerima nilai dari juri yang ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juriDitugaskan)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])
        ->assertRedirect();

    expect($this->match->judgeInputs()->count())->toBe(1);
});

it('menolak hukuman dari wasit yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitGelanggangLain)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertForbidden();

    expect($this->match->penalties()->count())->toBe(0);
});

it('menerima hukuman dari wasit yang ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitDitugaskan)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

it('tetap mengizinkan Dewan Wasit Juri menghukum tanpa ditugaskan ke partai', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->pengawas)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

it('menolak hitungan teknik dari wasit yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitGelanggangLain)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'hitungan' => 1,
        ])
        ->assertForbidden();
});

/*
 * Operator terikat gelanggang, bukan partai. Satu operator duduk di satu
 * gelanggang sepanjang hari, jadi penugasannya diberikan sekali per
 * gelanggang dan berlaku untuk seluruh partai yang dimainkan di sana.
 */
it('menolak operator gelanggang lain memulai babak', function () {
    [$gelanggangA, $gelanggangB] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangB->operator)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_TERJADWAL);
});

it('mengizinkan operator gelanggang partai memulai babak', function () {
    [$gelanggangA] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangA->operator)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak operator gelanggang lain mengakhiri partai', function () {
    [$gelanggangA, $gelanggangB] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangA->operator)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($gelanggangB->operator)
        ->post(route('admin.turnamen.partai.akhiri', [$this->tournament, $this->match]), [
            'corner' => 'red', 'sebab' => 'mutlak',
        ])
        ->assertForbidden();

    expect($this->match->fresh()->winner_registration_id)->toBeNull();
});

it('tetap mengizinkan Ketua Pertandingan mengendalikan timer lintas gelanggang', function () {
    ($this->siapkanGelanggang)();

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

/*
 * Peran di sistem ini biasanya tunggal, tapi tidak dijamin begitu: satu akun
 * bisa memegang wasit sekaligus juri. Yang diperiksa harus "ditugaskan di
 * partai ini dalam salah satu kapasitas", bukan "ditugaskan dalam SETIAP
 * kapasitas yang perannya izinkan" -- kalau tidak, wasit yang kebetulan juga
 * berperan juri ikut tertolak menjatuhkan hukuman di partai yang memang
 * ditugaskan kepadanya.
 */
it('mengizinkan pemegang dua peran yang ditugaskan sebagai salah satunya', function () {
    $rangkap = User::factory()->create();
    $rangkap->syncRoles(['wasit', 'juri']);

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $rangkap->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);

    ($this->mulaiBabak)();

    $this->actingAs($rangkap)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

it('tetap menolak pemegang dua peran yang tidak ditugaskan sama sekali', function () {
    $rangkap = User::factory()->create();
    $rangkap->syncRoles(['wasit', 'juri']);

    ($this->mulaiBabak)();

    $this->actingAs($rangkap)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertForbidden();

    expect($this->match->penalties()->count())->toBe(0);
});
