<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\PointerTayang;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Satu alamat per gelanggang, isinya mengikuti apa yang tayang.
 *
 * Sebelum berkas ini, panel per-gelanggang hanya mengenal Tanding: gelanggang
 * yang menayangkan Jurus mengirim SETIAP perannya ke layar tunggu yang
 * berbunyi "menunggu pengendali memilih partai" -- padahal pengendali sudah
 * memilih, dan nomornya sudah berjalan di matras. Juri yang membacanya
 * menyangka pengendalinya lupa.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);

    $this->event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $registrasi = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
    $registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $this->penampilan = JurusPerformance::create([
        'jurus_event_id' => $this->event->id,
        'registration_id' => $registrasi->id,
        'tahap' => 'final',
        'arena_id' => $this->arena->id,
        'order_in_arena' => 1,
    ]);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($this->pengendali->id);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->tayangkanJurus = function () {
        app(PointerTayang::class)->tunjukPenampilan(
            $this->arena,
            $this->penampilan->refresh(),
            $this->pengendali,
        );

        $this->arena->refresh();
    };
});

/*
 * Papan mode Jurus merender view OPERATOR yang sama, persis seperti papan mode
 * Tanding. Kendali timer Jurus dipegang Operator IT, dan alamat yang dibukanya
 * sepanjang hari adalah panel/papan -- view hanya-tampil di sini membuatnya
 * membuka alamatnya sendiri lalu tidak menemukan tombol Mulai.
 */
it('merender panel operator Jurus di alamat papan, bukan layar menunggu partai', function () {
    ($this->tayangkanJurus)();

    $this->actingAs(($this->buatUser)('operator-it'))
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('jurus.operator')
        ->assertSee('Mulai');
});

/*
 * Dan izinnya yang menyembunyikan tombolnya, bukan view kedua: juri membuka
 * alamat papan yang sama dan melihat papan tanpa kendali.
 */
it('menyembunyikan kendali timer dari yang tidak memegang izinnya', function () {
    ($this->tayangkanJurus)();

    $this->actingAs(($this->buatUser)('juri'))
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('jurus.operator')
        ->assertDontSee('>Mulai<', escape: false);
});

it('merender panel juri Jurus di alamat panel juri gelanggang', function () {
    ($this->tayangkanJurus)();

    $this->actingAs(($this->buatUser)('juri'))
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('jurus.juri');
});

it('merender panel ketua Jurus di alamat panel ketua gelanggang', function () {
    ($this->tayangkanJurus)();

    $this->actingAs(($this->buatUser)('ketua-pertandingan'))
        ->get(route('admin.turnamen.gelanggang.panel.ketua', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('jurus.panel-ketua');
});

/*
 * Wasit tidak bertugas di Jurus. 404 di situ membuatnya menyangka panelnya
 * rusak; layar tunggu yang menyebut sebabnya membuatnya tahu ia cuma perlu
 * menunggu.
 */
it('memberi wasit layar tunggu yang menyebutkan sebabnya, bukan 404', function () {
    ($this->tayangkanJurus)();

    $this->actingAs(($this->buatUser)('wasit'))
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('silat.menunggu-partai')
        ->assertSee('sedang menayangkan Jurus');
});

it('tetap merender panel kendali yang sama di kedua mode', function () {
    ($this->tayangkanJurus)();

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewIs('silat.kendali');
});

it('mengembalikan panel Tanding setelah gelanggang dikosongkan dari Jurus', function () {
    ($this->tayangkanJurus)();

    app(PointerTayang::class)->kosongkan($this->arena, $this->pengendali);

    $this->actingAs(($this->buatUser)('juri'))
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena->refresh()]))
        ->assertOk()
        ->assertViewIs('silat.menunggu-partai');
});

/*
 * Panel Tanding yang sedang terbuka harus tahu gelanggangnya beralih ke Jurus.
 * `match` yang jadi null saja tidak cukup: itu juga bunyi gelanggang kosong,
 * dan keduanya menuntut halaman yang berbeda.
 */
it('menyebutkan jenis tayangan gelanggang di muatan state', function () {
    ($this->tayangkanJurus)();

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('panel.tayang', 'jurus');
});

it('menandai panel selain kendali supaya mengikuti pergantian tayangan', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertViewHas('config', fn (array $config) => $config['ikutiTayang'] === false);
});

/** Janji "satu alamat per gelanggang": mode baru tidak menambah alamat. */
it('tidak menambah alamat panel gelanggang', function () {
    $jumlah = collect(app('router')->getRoutes()->getRoutesByName())
        ->keys()
        ->filter(fn (string $nama) => str_starts_with($nama, 'admin.turnamen.gelanggang.panel.'))
        ->count();

    expect($jumlah)->toBe(19);
});
