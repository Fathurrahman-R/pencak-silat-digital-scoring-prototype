<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
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

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->sekretariat = User::factory()->create();
    $this->sekretariat->syncRoles(['sekretariat']);
});

it('menampilkan halaman rekap', function () {
    $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.index', $this->tournament))
        ->assertOk()
        ->assertSee('Peringkat umum kontingen');
});

it('mengekspor rekap medali sebagai PDF', function () {
    $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.ekspor.medali-pdf', $this->tournament))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('mengekspor rekap medali sebagai CSV', function () {
    $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.ekspor.medali', $this->tournament))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('mengekspor daftar peserta sebagai CSV', function () {
    $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.ekspor.peserta', $this->tournament))
        ->assertOk();
});

it('mengekspor jadwal sebagai CSV', function () {
    $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.ekspor.jadwal', $this->tournament))
        ->assertOk();
});

it('user tanpa izin rekap ditolak', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->get(route('admin.turnamen.rekap.index', $this->tournament))
        ->assertForbidden();
});

/*
 * --------------------------------------------------------------------
 * Rekap tanpa emoji medali
 * --------------------------------------------------------------------
 */

it('menampilkan medali sebagai kolom berjudul, bukan emoji', function () {
    /*
     * Emoji medali dirender berbeda di tiap sistem — datar di Windows, timbul
     * di iOS, kadang kotak kosong — dan tidak pernah jadi bagian sistem
     * desain, jadi tidak ada satu pun angka kontras yang berlaku untuknya.
     *
     * Layar ini dipakai menyusun berita acara; ia justru paling tidak boleh
     * bergantung pada glif yang bisa hilang di perangkat panitia.
     *
     * Medalinya dibuat lewat partai final yang benar-benar disahkan, bukan
     * data karangan yang disuntik ke view — supaya uji ini ikut menjaga jalur
     * yang menghasilkan angkanya.
     */
    $kontingen = Contingent::factory()->for($this->tournament)->create(['name' => 'Padepokan Uji']);
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $peserta = function (string $nama) use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => $nama]));

        return $reg;
    };

    $juara = $peserta('Juara Emas');
    $runner = $peserta('Juara Perak');

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $juara->id, 'blue_registration_id' => $runner->id,
        'winner_registration_id' => $juara->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI,
        'ratified_at' => now(), 'ratified_by' => $this->sekretariat->id,
    ]);

    $halaman = $this->actingAs($this->sekretariat)
        ->get(route('admin.turnamen.rekap.index', $this->tournament))
        ->assertOk();

    $halaman
        ->assertDontSee('🥇')
        ->assertDontSee('🥈')
        ->assertDontSee('🥉')
        // Kolom berjudul, dan jumlah yang tidak perlu dihitung sendiri.
        ->assertSee('Jumlah')
        ->assertSee('Padepokan Uji')
        // Emas dan perak disebut sebagai kata, bukan gambar.
        ->assertSee('Juara Emas')
        ->assertSee('Juara Perak');
});
