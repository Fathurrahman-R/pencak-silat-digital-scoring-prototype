<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Bracket;
use App\Models\Contingent;
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

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };
});

it('menampilkan panel operator dan menyisipkan konfigurasi alamat aksi', function () {
    $operator = ($this->buatUser)('operator-it');

    $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('OPERATOR GELANGGANG')
        ->assertSee('partaiPanel', false)
        ->assertSee('timerMulai', false);
});

it('menampilkan panel wasit', function () {
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('WASIT');
});

it('menampilkan panel dewan juri', function () {
    $pengawas = ($this->buatUser)('pengawas-wasit-juri');

    $this->actingAs($pengawas)
        ->get(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('DEWAN JURI');
});

it('mengizinkan juri melihat panel operator sebagai pemantau, meski tombolnya tersembunyi lewat @resource', function () {
    // Juri memang punya partai.view (memantau jalannya partai) tapi bukan
    // partai.update/manage, jadi tombol kendali di halaman ini tersembunyi.
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertOk();
});

it('menolak pengguna tanpa peran pertandingan membuka panel operator', function () {
    $tanpaPeran = User::factory()->create();

    $this->actingAs($tanpaPeran)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menolak juri membuka panel wasit', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menampilkan panel juri lengkap dengan tautan manifest PWA', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('partaiPanel', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('admin.turnamen.partai.juri.manifest', [$this->tournament, $this->match]), false);
});

it('menolak wasit membuka panel juri -- itu bukan resource penilaian.create miliknya', function () {
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menyajikan manifest PWA per partai dengan start_url menunjuk balik ke partai itu', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri.manifest', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertJsonPath('display', 'fullscreen');
});
