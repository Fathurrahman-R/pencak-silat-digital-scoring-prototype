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
 * Jadwal hanya boleh disentuh selama partainya belum dimulai.
 *
 * Melepas partai yang sedang berlangsung dari gelanggangnya mendamparkannya:
 * papan skor publik gelanggang itu mendadak kosong di tengah pertandingan,
 * dan tidak ada operator yang bisa menjeda, menyelesaikan babak, atau
 * mengakhirinya -- kewenangan operator justru diikat ke gelanggang partai.
 *
 * Partai yang sudah selesai juga tidak boleh dilepas: gelanggang dan urutan
 * tayangnya bagian dari catatan hasil yang masuk berita acara.
 */
beforeEach(function () {
    $this->ketua = User::factory()->create();
    $this->ketua->syncRoles(['ketua-pertandingan']);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->gelanggang = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $this->gelanggangLain = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B']);

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $sudut = collect(['merah', 'biru'])->map(function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    });

    $this->buatPartai = fn (string $status) => SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'arena_id' => $this->gelanggang->id, 'order_in_arena' => 1,
        'red_registration_id' => $sudut[0]->id, 'blue_registration_id' => $sudut[1]->id,
        'status' => $status,
    ]);
});

it('menolak melepas partai yang sedang berlangsung', function () {
    $partai = ($this->buatPartai)(SilatMatch::STATUS_BERLANGSUNG);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partai]))
        ->assertSessionHas('error');

    expect($partai->fresh()->arena_id)->toBe($this->gelanggang->id);
});

it('menolak melepas partai yang sudah selesai', function () {
    $partai = ($this->buatPartai)(SilatMatch::STATUS_SELESAI);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partai]))
        ->assertSessionHas('error');

    expect($partai->fresh()->arena_id)->toBe($this->gelanggang->id);
});

it('menolak menggeser urutan partai yang sedang berlangsung', function () {
    $partai = ($this->buatPartai)(SilatMatch::STATUS_BERLANGSUNG);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jadwal.pindahkan', [$this->tournament, $partai]), ['urutan' => 3])
        ->assertSessionHas('error');

    expect($partai->fresh()->order_in_arena)->toBe(1);
});

it('menolak menjadwalkan ulang partai yang sudah selesai', function () {
    $partai = ($this->buatPartai)(SilatMatch::STATUS_SELESAI);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
            'arena_id' => $this->gelanggangLain->id,
        ])
        ->assertSessionHas('error');

    expect($partai->fresh()->arena_id)->toBe($this->gelanggang->id);
});

it('tetap mengizinkan melepas partai yang belum dimulai', function () {
    $partai = ($this->buatPartai)(SilatMatch::STATUS_TERJADWAL);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partai]))
        ->assertSessionHas('success');

    expect($partai->fresh()->arena_id)->toBeNull();
});
