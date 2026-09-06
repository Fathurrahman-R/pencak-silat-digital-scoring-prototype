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
use App\Support\Bagan\KesiapanHulu;
use App\Support\Gelanggang\PointerPartaiAktif;
use App\Support\Scoring\MatchTimer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Endpoint sinkron -- pintu yang dilewati data kejuaraan lengkap.
 *
 * Yang dijaga di sini bukan kebenaran isinya (itu urusan PaketSinkronTest)
 * melainkan siapa yang boleh mengetuk. Endpoint ini menyajikan seluruh riwayat
 * pertandingan, dan ia hidup di LAN yang sama dengan perangkat penonton.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.token' => 'rahasia-uji',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
});

it('menolak paket tanpa token', function () {
    $this->getJson('/sinkron/paket')->assertForbidden();
});

it('menolak paket dengan token keliru', function () {
    $this->withHeader('X-Sinkron-Token', 'bukan-token')
        ->getJson('/sinkron/paket')
        ->assertForbidden();
});

it('melayani paket saat token cocok', function () {
    $this->withHeader('X-Sinkron-Token', 'rahasia-uji')
        ->getJson('/sinkron/paket')
        ->assertOk()
        ->assertJsonStructure(['node', 'peran', 'kursor', 'selesai', 'baris']);
});

/*
 * Token kosong berarti endpoint tidak hidup sama sekali, bukan terbuka untuk
 * semua. Laptop yang baru dipasang belum punya token, dan kalau kosong
 * diartikan "tidak perlu diperiksa", ia akan menyajikan seluruh basis datanya
 * ke siapa pun di jaringan gelanggang.
 */
it('mematikan endpoint sepenuhnya saat token belum diisi', function () {
    config(['sinkron.token' => '']);

    $this->getJson('/sinkron/paket')->assertNotFound();
    $this->withHeader('X-Sinkron-Token', 'apa pun')->getJson('/sinkron/paket')->assertNotFound();
});

it('menyebutkan identitas node kepada peer yang membawa token', function () {
    $this->withHeader('X-Sinkron-Token', 'rahasia-uji')
        ->getJson('/sinkron/identitas')
        ->assertOk()
        ->assertJson(['node' => 'gelanggang-a', 'peran' => 'gelanggang', 'arena' => ['A']]);
});

/*
 * Halaman operator dijaga izin biasa, BUKAN token: yang membukanya orang yang
 * sudah login, bukan mesin.
 */
it('menolak halaman sinkron untuk akun tanpa izin', function () {
    $pengguna = User::factory()->create();
    $pengguna->syncRoles(['juri']);

    $this->actingAs($pengguna)->get(route('admin.sinkron.index'))->assertForbidden();
});

it('membuka halaman sinkron untuk pengendali gelanggang', function () {
    $pengendali = User::factory()->create();
    $pengendali->syncRoles(['pengendali-gelanggang']);

    $this->actingAs($pengendali)
        ->get(route('admin.sinkron.index'))
        ->assertOk()
        ->assertSee('gelanggang-a');
});

/*
 * Penjaga partai hulu, diuji lewat jalur yang benar-benar dipakai pengendali:
 * memilih partai di panel gelanggang.
 */
it('menolak menayangkan partai yang hulunya di gelanggang lain belum sampai', function () {
    $arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $partai = fn (int $babak, int $posisi, Arena $arena) => SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => $babak, 'position' => $posisi,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $arena->id, 'order_in_arena' => $posisi,
    ]);

    // Dua semifinal berjalan di gelanggang B, finalnya di gelanggang A.
    $partai(1, 1, $arenaB);
    $partai(1, 2, $arenaB);
    $final = $partai(2, 1, $this->arenaA);

    $pengendali = User::factory()->create();
    $pengendali->syncRoles(['pengendali-gelanggang']);

    $pointer = new PointerPartaiAktif(new MatchTimer, app(KesiapanHulu::class));

    expect(fn () => $pointer->tunjuk($this->arenaA, $final, $pengendali))
        ->toThrow(RuntimeException::class, 'Gelanggang B');

    // Pintu darurat tetap ada: panitia yang sudah tahu hasilnya dari
    // gelanggang sebelah tidak boleh terkunci menunggu jaringan.
    $pointer->tunjuk($this->arenaA, $final, $pengendali, paksa: true);

    expect($this->arenaA->fresh()->active_match_id)->toBe($final->id);
});
