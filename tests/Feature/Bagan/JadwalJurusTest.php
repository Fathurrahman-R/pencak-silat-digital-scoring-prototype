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
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\SusunBaganJurus;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 1']);

    $this->nomor = JurusEvent::where('tournament_id', $this->tournament->id)
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $this->nomor->update(['format' => FormatJurus::Battle]);

    $this->daftarkan = function (int $jumlah) {
        return collect(range(1, $jumlah))->map(function () {
            $reg = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => null, 'jurus_event_id' => $this->nomor->id]);
            $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

            return $reg->refresh();
        });
    };

    $this->panitia = User::factory()->create();
    $this->panitia->assignRole('super-admin');

    $this->susun = new SusunBaganJurus;
});

it('menampilkan tab Jurus berisi battle yang siap dijadwalkan', function () {
    ($this->daftarkan)(4);
    $this->susun->untukNomor($this->nomor, acak: false);

    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jadwal.jurus.index', $this->tournament))
        ->assertOk()
        ->assertSee('Battle belum dijadwalkan')
        ->assertSee('Gelanggang 1');
});

it('tidak menampilkan battle yang sudutnya belum lengkap', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    // Final belum punya penghuni: ia tidak boleh muncul sebagai baris siap jadwal.
    $final = $bagan->battles()->where('round', 2)->sole();

    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jadwal.jurus.index', $this->tournament))
        ->assertOk()
        ->assertDontSee(route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$this->tournament, $final]));
});

it('menjadwalkan battle lewat rute, dua sudutnya masuk antrean gelanggang', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->actingAs($this->panitia)
        ->post(route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$this->tournament, $battle]), [
            'arena_id' => $this->arena->id,
        ])
        ->assertRedirect();

    expect(JurusPerformance::where('arena_id', $this->arena->id)->count())->toBe(2)
        ->and($battle->refresh()->arena_id)->toBe($this->arena->id);
});

it('menolak melepas satu sudut battle, dan menyuruh melepas battle-nya', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->actingAs($this->panitia)->post(
        route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$this->tournament, $battle]),
        ['arena_id' => $this->arena->id],
    );

    $satuSudut = JurusPerformance::where('jurus_battle_id', $battle->id)->firstOrFail();

    $this->actingAs($this->panitia)
        ->post(route('admin.turnamen.jadwal.jurus.penampilan.lepas', [$this->tournament, $satuSudut]))
        ->assertSessionHas('error');

    expect($satuSudut->refresh()->arena_id)->toBe($this->arena->id);
});

it('melepas battle beserta kedua sudutnya lewat rute', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->actingAs($this->panitia)->post(
        route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$this->tournament, $battle]),
        ['arena_id' => $this->arena->id],
    );

    $this->actingAs($this->panitia)
        ->post(route('admin.turnamen.jadwal.jurus.battle.lepas', [$this->tournament, $battle]))
        ->assertSessionHas('success');

    expect(JurusPerformance::whereNotNull('arena_id')->count())->toBe(0);
});

it('menjadwalkan penampilan lepas untuk nomor berformat peringkat', function () {
    $this->nomor->update(['format' => FormatJurus::Penampilan]);
    $reg = ($this->daftarkan)(1)->first();

    $penampilan = JurusPerformance::create([
        'jurus_event_id' => $this->nomor->id,
        'registration_id' => $reg->id,
        'tahap' => 'final',
    ]);

    $this->actingAs($this->panitia)
        ->post(route('admin.turnamen.jadwal.jurus.penampilan.tetapkan', [$this->tournament, $penampilan]), [
            'arena_id' => $this->arena->id,
        ])
        ->assertSessionHas('success');

    expect($penampilan->refresh()->order_in_arena)->toBe(1);
});

it('mencetak jadwal Jurus sebagai PDF', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->actingAs($this->panitia)->post(
        route('admin.turnamen.jadwal.jurus.battle.tetapkan', [$this->tournament, $battle]),
        ['arena_id' => $this->arena->id],
    );

    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jadwal.jurus.cetak', $this->tournament))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('menolak pengguna tanpa izin jadwal membuka tab Jurus', function () {
    $orang = User::factory()->create();
    $orang->assignRole('juri');

    $this->actingAs($orang)
        ->get(route('admin.turnamen.jadwal.jurus.index', $this->tournament))
        ->assertForbidden();
});

it('tab Tanding tetap utuh dan menautkan tab Jurus', function () {
    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jadwal.index', $this->tournament))
        ->assertOk()
        ->assertSee(route('admin.turnamen.jadwal.jurus.index', $this->tournament));
});
