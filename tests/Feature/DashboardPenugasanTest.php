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

/*
 * Penanda ringkasan berubah dari 'Pengguna baru' dan 'Mulai dari mana' -- dua
 * judul warisan boilerplate (grafik pendaftaran pengguna dan panduan membuat
 * Resource) yang dibuang saat dashboard diarahkan ke isi kejuaraan. Penggantinya
 * adalah judul yang benar-benar berarti bagi panitia.
 */
it('menyembunyikan ringkasan pengelolaan kejuaraan dari aparat pertandingan', function () {
    $juri = ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);

    $this->actingAs($juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Partai hari ini')
        ->assertDontSee('Urutan kerja kejuaraan');
});

it('tetap menampilkan ringkasan untuk pengelola kejuaraan', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Urutan kerja kejuaraan')
        ->assertDontSee('Partai saya');
});

/*
 * Alamat per-partai basi begitu pengendali memindahkan jadwal: petugas yang
 * menekan kartu lama mendarat di partai yang sudah lewat. Kartu karena itu
 * menunjuk gelanggangnya, yang tidak pernah basi.
 */
it('menautkan ke panel gelanggang untuk partai yang sudah dijadwalkan', function () {
    $gelanggang = App\Models\Arena::factory()->for($this->tournament)->create();
    $this->match->update(['arena_id' => $gelanggang->id, 'order_in_arena' => 1]);

    $juri = ($this->tugaskan)('juri', MatchOfficial::ROLE_JURI, 1);

    $this->actingAs($juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $gelanggang]), false)
        ->assertDontSee(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]), false);
});
