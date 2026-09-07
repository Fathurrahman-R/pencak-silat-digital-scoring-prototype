<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\SusunBaganJurus;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Penanda latensi di penanda sambungan gelanggang.
 *
 * Dua janji yang dikunci di sini, dan keduanya mudah hanyut.
 *
 * Pertama, angkanya muncul di SELURUH panel petugas -- termasuk dua panel
 * Jurus yang selama ini terlewat tidak memasang penanda sambungan sama
 * sekali. Panel yang kehilangan penandanya tidak mengeluh; ia hanya diam
 * sementara petugasnya menekan tombol ke jaringan yang sudah melambat.
 *
 * Kedua, angkanya TIDAK muncul di layar penonton. Milidetik adalah angka
 * teknis untuk aparat, bukan untuk papan yang dipandangi satu gelanggang
 * penuh, dan penanda tersambung/terputus di sana harus tetap utuh tanpanya.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);
});

it('memasang penanda latensi di panel petugas Tanding', function (string $rute) {
    $this->actingAs($this->admin)
        ->get(route($rute, [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('data-latensi', false);
})->with([
    'admin.turnamen.gelanggang.panel.kendali',
    'admin.turnamen.gelanggang.panel.juri',
    'admin.turnamen.gelanggang.panel.wasit',
    'admin.turnamen.gelanggang.panel.papan',
    'admin.turnamen.gelanggang.panel.dewan-juri',
    'admin.turnamen.gelanggang.panel.ketua',
    'admin.turnamen.gelanggang.panel.komisi-protes',
]);

/*
 * Panel Jurus memakai layout gelanggang yang sama, tapi `operator` dan
 * `battle` sebelumnya tidak memasang penanda sambungan sama sekali. Uji ini
 * menjaganya tetap terpasang.
 */
it('memasang penanda latensi di panel petugas Jurus', function () {
    $nomor = JurusEvent::where('tournament_id', $this->tournament->id)
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $nomor->update(['format' => FormatJurus::Battle]);

    collect(range(1, 4))->each(function () use ($nomor) {
        $reg = Registration::factory()->for($this->kontingen)->terverifikasi()
            ->create(['weight_class_id' => null, 'jurus_event_id' => $nomor->id]);
        $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());
    });

    $susun = new SusunBaganJurus;
    $bagan = $susun->untukNomor($nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();
    $susun->siapkanPenampilan($battle);

    $penampilan = $battle->performances()->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.jurus.battle', [$this->tournament, $battle]))
        ->assertOk()
        ->assertSee('data-latensi', false);

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.jurus.penampilan.operator', [$this->tournament, $penampilan]))
        ->assertOk()
        ->assertSee('data-latensi', false);
});

it('tidak menampilkan angka latensi di layar penonton, tapi tetap menampilkan penanda sambungan', function () {
    config(['live.enabled' => true]);

    $this->get(route('live.gelanggang', ['arena' => $this->arena]))
        ->assertOk()
        ->assertSee('$store.koneksi', false)
        ->assertDontSee('data-latensi', false);
});
