<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->registrasi = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
    $this->registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => 'Sari']));

    $this->performance = JurusPerformance::create([
        'jurus_event_id' => $this->event->id, 'registration_id' => $this->registrasi->id, 'tahap' => 'final',
    ]);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };

    $this->operator = ($this->buatUser)('operator-it');
    $this->pengawas = ($this->buatUser)('pengawas-wasit-juri');
    $this->juri = ($this->buatUser)('juri');
    $this->ketua = ($this->buatUser)('ketua-pertandingan');
});

it('membuat penampilan untuk pendaftaran terverifikasi yang belum punya penampilan', function () {
    $kontingen2 = Contingent::factory()->for($this->tournament)->create();
    $registrasi2 = Registration::factory()->for($kontingen2)->terverifikasi()
        ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
    $registrasi2->athletes()->attach(Athlete::factory()->for($kontingen2)->create());

    $this->actingAs($this->operator)
        ->post(route('admin.turnamen.jurus.generate', [$this->tournament, $this->event]), ['tahap' => 'final'])
        ->assertRedirect();

    expect(JurusPerformance::where('jurus_event_id', $this->event->id)->count())->toBe(2);
});

it('operator bisa memulai dan menghentikan timer', function () {
    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.mulai', [$this->tournament, $this->performance]))
        ->assertOk();

    expect($this->performance->fresh()->status)->toBe(JurusPerformance::STATUS_BERLANGSUNG);

    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.berhenti', [$this->tournament, $this->performance]))
        ->assertOk();

    expect($this->performance->fresh()->status)->toBe(JurusPerformance::STATUS_SELESAI);
});

it('juri bisa mengirim dan memperbarui nilainya sendiri', function () {
    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]), ['value' => 9.70])
        ->assertOk();

    expect(JurusScore::where('performance_id', $this->performance->id)->where('judge_user_id', $this->juri->id)->value('value'))
        ->toEqual(9.70);

    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]), ['value' => 9.80])
        ->assertOk();

    expect(JurusScore::where('performance_id', $this->performance->id)->where('judge_user_id', $this->juri->id)->count())->toBe(1)
        ->and(JurusScore::where('performance_id', $this->performance->id)->where('judge_user_id', $this->juri->id)->value('value'))
        ->toEqual(9.80);
});

it('menolak nilai di luar skala 9.00-10.00', function () {
    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]), ['value' => 8.50])
        ->assertUnprocessable();
});

it('pengawas bisa mencatat pengurangan 0.50 dan menetapkan diskualifikasi', function () {
    $this->actingAs($this->pengawas)
        ->postJson(route('admin.turnamen.jurus.penampilan.pengurangan-pengawas', [$this->tournament, $this->performance]), ['alasan' => 'keluar gelanggang'])
        ->assertOk();

    expect($this->performance->deductions()->berlaku()->sum('jumlah'))->toEqual(0.50);

    $this->actingAs($this->pengawas)
        ->postJson(route('admin.turnamen.jurus.penampilan.diskualifikasi', [$this->tournament, $this->performance]))
        ->assertOk();

    expect($this->performance->fresh()->didiskualifikasi)->toBeTrue();
});

it('juri tanpa izin pengurangan-jurus ditolak mencatat pengurangan pengawas', function () {
    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.jurus.penampilan.pengurangan-pengawas', [$this->tournament, $this->performance]), ['alasan' => 'x'])
        ->assertForbidden();
});

it('ketua pertandingan bisa mengesahkan penampilan dengan juri lengkap dan genap', function () {
    $this->performance->update(['status' => JurusPerformance::STATUS_SELESAI]);
    foreach (range(1, 4) as $i) {
        JurusScore::create(['performance_id' => $this->performance->id, 'judge_user_id' => User::factory()->create()->id, 'value' => 9.70]);
    }

    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertOk();

    expect($this->performance->fresh()->disahkan())->toBeTrue();
});

it('menolak mengesahkan penampilan yang belum selesai', function () {
    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertUnprocessable();
});

it('menolak mengesahkan penampilan dengan juri kurang dari minimal', function () {
    $this->performance->update(['status' => JurusPerformance::STATUS_SELESAI]);
    JurusScore::create(['performance_id' => $this->performance->id, 'judge_user_id' => $this->juri->id, 'value' => 9.70]);

    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertUnprocessable();
});

it('menolak mengesahkan penampilan dengan jumlah juri ganjil', function () {
    $this->performance->update(['status' => JurusPerformance::STATUS_SELESAI]);
    foreach (range(1, 5) as $i) {
        JurusScore::create(['performance_id' => $this->performance->id, 'judge_user_id' => User::factory()->create()->id, 'value' => 9.70]);
    }

    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertUnprocessable();
});

it('diskualifikasi bisa disahkan walau juri belum lengkap', function () {
    $this->performance->update(['status' => JurusPerformance::STATUS_SELESAI, 'didiskualifikasi' => true]);

    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertOk();
});
