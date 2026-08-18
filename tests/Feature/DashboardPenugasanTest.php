<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
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

/*
 * Panel wasit dan juri terikat ke satu partai, sehingga tidak pernah muncul di
 * sidebar — alamatnya tidak bisa dibentuk tanpa tahu partai mana. Kartu
 * "Partai saya" di dashboard adalah satu-satunya pintu masuk mereka dari dalam
 * aplikasi, dan itu sebabnya perilakunya diuji.
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

    $this->tugaskan = function (string $peran, string $roleAparat, ?int $nomor = null): User {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        MatchOfficial::create([
            'match_id' => $this->match->id,
            'user_id' => $user->id,
            'role' => $roleAparat,
            'number' => $nomor,
        ]);

        return $user;
    };
});

it('menampilkan partai yang ditugaskan beserta tautan ke panel juri', function () {
    $juri = ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);

    $this->actingAs($juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Partai saya')
        ->assertSee('Juri 1')
        ->assertSee(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]), false);
});

it('mengarahkan wasit ke panel wasit, bukan panel juri', function () {
    $wasit = ($this->tugaskan)('wasit', MatchOfficial::ROLE_WASIT);

    $this->actingAs($wasit)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]), false)
        ->assertDontSee(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]), false);
});

it('tidak menampilkan partai milik aparat lain', function () {
    ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);

    $juriLain = User::factory()->create();
    $juriLain->syncRoles(['juri']);

    $this->actingAs($juriLain)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Partai saya')
        ->assertSee('Belum ada partai untuk Anda');
});

it('menyembunyikan partai yang sudah selesai', function () {
    $juri = ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Partai saya');
});

it('menyembunyikan ringkasan pengelolaan aplikasi dari aparat pertandingan', function () {
    $juri = ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);

    $this->actingAs($juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Pengguna baru')
        ->assertDontSee('Mulai dari mana');
});

it('tetap menampilkan ringkasan untuk pengelola pengguna', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pengguna baru')
        ->assertDontSee('Partai saya');
});
