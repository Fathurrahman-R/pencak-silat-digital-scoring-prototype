<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
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

/**
 * Cetakan jadwal dipakai di meja panitia gelanggang. Menulis "Kelas A" untuk
 * partai putra maupun putri membuat dua baris yang tidak bisa dibedakan --
 * padahal ekspor peserta sudah benar menulis golongan dan jenis kelaminnya.
 */
beforeEach(function () {
    $this->panitia = User::factory()->create();
    $this->panitia->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $gelanggang = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $buatPartai = function (JenisKelamin $jenis, int $urutan) use ($kontingen, $gelanggang) {
        $kelas = $this->tournament->weightClasses()
            ->untuk(GolonganUsia::Dewasa, $jenis)->where('code', 'A')->firstOrFail();

        $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

        $sudut = collect(['merah', 'biru'])->map(function () use ($kontingen, $kelas) {
            $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
            $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

            return $r;
        });

        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
            'arena_id' => $gelanggang->id, 'order_in_arena' => $urutan,
            'red_registration_id' => $sudut[0]->id, 'blue_registration_id' => $sudut[1]->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
        ]);
    };

    $buatPartai(JenisKelamin::Putra, 1);
    $buatPartai(JenisKelamin::Putri, 2);
});

it('membedakan kelas putra dan putri di ekspor jadwal', function () {
    $isi = $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.rekap.ekspor.jadwal', $this->tournament))
        ->assertOk()
        ->streamedContent();

    expect($isi)->toContain('Putra Dewasa Kelas A')
        ->and($isi)->toContain('Putri Dewasa Kelas A');
});

it('tidak menyisakan kolom kelas yang ambigu', function () {
    $isi = $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.rekap.ekspor.jadwal', $this->tournament))
        ->assertOk()
        ->streamedContent();

    // Dua baris data, dan tidak satu pun cuma bertulis "Kelas A".
    expect($isi)->not->toContain(',"Kelas A",');
});
