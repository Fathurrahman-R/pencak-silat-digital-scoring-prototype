<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
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

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($this->kontingen)->create(['name' => 'Merah Satu']));

    $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($this->kontingen)->create(['name' => 'Biru Satu']));

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->wasit = User::factory()->create();
    $this->wasit->syncRoles(['wasit']);

    $this->juri = User::factory()->count(4)->create();
    $this->juri->each(fn ($u) => $u->syncRoles(['juri']));
});

it('menampilkan halaman penugasan aparat', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.partai.aparat.show', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Merah Satu')
        ->assertSee('Biru Satu');
});

it('menetapkan wasit dan juri sesuai jumlah yang disyaratkan', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => $this->juri->take(3)->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->match->officials()->where('role', MatchOfficial::ROLE_WASIT)->count())->toBe(1)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_JURI)->count())->toBe(3)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_JURI)->pluck('number')->sort()->values()->all())
        ->toBe([1, 2, 3]);
});

it('menolak jumlah juri yang tidak sesuai setelan peraturan', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => $this->juri->take(2)->pluck('id')->all(),
        ])
        ->assertSessionHasErrors('juri_id');
});

it('menolak wasit yang merangkap juri', function () {
    $rangkap = $this->juri->first();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $rangkap->id,
            'juri_id' => $this->juri->take(3)->pluck('id')->all(),
        ])
        ->assertSessionHasErrors('wasit_id');
});

it('menolak juri yang sama ditugaskan dua kali', function () {
    $duplikat = $this->juri->first()->id;

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$duplikat, $duplikat, $this->juri->last()->id],
        ])
        ->assertSessionHasErrors('juri_id.0');
});

it('menimpa penugasan lama saat ditetapkan ulang', function () {
    $this->actingAs($this->admin)->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
        'wasit_id' => $this->wasit->id,
        'juri_id' => $this->juri->take(3)->pluck('id')->all(),
    ]);

    $wasitBaru = User::factory()->create();
    $wasitBaru->syncRoles(['wasit']);

    $this->actingAs($this->admin)->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
        'wasit_id' => $wasitBaru->id,
        'juri_id' => $this->juri->reverse()->take(3)->pluck('id')->all(),
    ]);

    expect($this->match->officials()->count())->toBe(4)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_WASIT)->first()->user_id)->toBe($wasitBaru->id);
});

it('menolak partai yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.partai.aparat.show', [$turnamenLain, $this->match]))
        ->assertNotFound();
});
