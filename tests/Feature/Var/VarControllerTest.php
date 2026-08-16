<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
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
use App\Models\VarReview;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

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

    $this->ketuaPertandingan = User::factory()->create();
    $this->ketuaPertandingan->syncRoles(['ketua-pertandingan']);
});

it('ketua pertandingan bisa mengajukan dan memutuskan protes VAR lewat HTTP', function () {
    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.var.ajukan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'kejadian' => 'jatuhan tidak dihitung',
        ])->assertOk();

    $review = VarReview::firstOrFail();
    expect($review->corner->value)->toBe('red');

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.var.putuskan', [$this->tournament, $this->match, $review]), [
            'keputusan' => 'sah', 'catatan' => 'dikonfirmasi tayangan ulang',
        ])->assertOk();

    expect($review->fresh()->keputusan)->toBe('sah');
});

it('user tanpa permission var ditolak mengajukan protes', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->postJson(route('admin.turnamen.partai.keberatan.var.ajukan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'kejadian' => 'kejadian',
        ])->assertForbidden();
});

it('mengajukan dan memutuskan protes manajer tingkat pertama lewat HTTP', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'ditolak',
        ])->assertOk();

    expect($protes->fresh()->keputusan)->toBe('ditolak');
});
