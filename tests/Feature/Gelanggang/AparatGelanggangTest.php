<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\MatchOfficial;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Penugasan aparat per GELANGGANG, dan satu-satunya tempatnya.
 *
 * Tabel `arena_officials` beserta penyalinannya ke `match_officials` sudah ada
 * sejak 5 September 2026, lengkap dengan pendaratan dasbor yang membacanya --
 * tapi TIDAK ADA satu pun kode yang pernah menulis ke tabel itu. Tidak ada
 * layar, tidak ada rute, tidak ada seeder. Persis bentuk cacat yang sama
 * dengan `arena_id` pada penampilan Jurus: mesinnya lengkap, pintunya tidak
 * pernah dibuat, dan dokumentasinya sudah menjelaskan alur yang belum ada.
 *
 * Menugaskan partai demi partai adalah empat baris dikali empat puluh partai
 * untuk meja panitia, padahal orang yang duduk di kursi Juri 1 Gelanggang A
 * pagi ini duduk di sana sampai sore.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    // Jumlah kursi juri dibaca dari setelan peraturan kejuaraan, dan setelan
    // itu lahir bersama master datanya.
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $this->berperan = function (string $peran, string $nama = 'Petugas') {
        $user = User::factory()->create(['name' => $nama, 'is_active' => true]);
        $user->syncRoles([$peran]);

        return $user;
    };

    $this->penugas = ($this->berperan)('ketua-pertandingan', 'Ketua');

    $this->simpan = fn (Arena $arena, array $muatan) => $this->actingAs($this->penugas)
        ->post(route('admin.turnamen.gelanggang.aparat', [$this->tournament, $arena]), $muatan);
});

it('menyimpan kursi wasit dan juri bernomor untuk satu gelanggang', function () {
    $wasit = ($this->berperan)('ketua-pertandingan', 'Wasit Gelanggang A');
    $juri = collect(range(1, 3))->map(fn ($n) => ($this->berperan)('juri', "Juri {$n} Gelanggang A"));

    ($this->simpan)($this->arenaA, [
        'wasit_id' => $wasit->id,
        'juri_id' => $juri->pluck('id')->all(),
    ])->assertRedirect();

    $kursi = ArenaOfficial::where('arena_id', $this->arenaA->id)->get();

    expect($kursi)->toHaveCount(4)
        ->and($kursi->firstWhere('role', MatchOfficial::ROLE_WASIT)->user_id)->toBe($wasit->id);

    foreach ($juri as $i => $orang) {
        $baris = $kursi->where('role', MatchOfficial::ROLE_JURI)->firstWhere('number', $i + 1);

        expect($baris?->user_id)->toBe($orang->id, 'kursi juri '.($i + 1).' tidak terisi');
    }
});

/*
 * Kursi kosong bukan kesalahan: gelanggang yang juri ketiganya belum datang
 * tetap harus bisa disimpan. Yang menolak partai berjalan tanpa juri lengkap
 * adalah penjagaan di panel, bukan formulir ini.
 */
it('menerima kursi yang dikosongkan', function () {
    $wasit = ($this->berperan)('ketua-pertandingan', 'Wasit');

    ($this->simpan)($this->arenaA, ['wasit_id' => $wasit->id, 'juri_id' => [null, null, null]])
        ->assertRedirect();

    expect(ArenaOfficial::where('arena_id', $this->arenaA->id)->count())->toBe(1);
});

it('menolak orang yang perannya tidak sesuai kursinya', function () {
    $official = ($this->berperan)('official-kontingen', 'Official Kontingen');

    ($this->simpan)($this->arenaA, ['wasit_id' => $official->id])
        ->assertSessionHasErrors('wasit_id');

    expect(ArenaOfficial::where('arena_id', $this->arenaA->id)->count())->toBe(0);
});

it('menolak satu orang menduduki dua kursi juri di gelanggang yang sama', function () {
    $satu = ($this->berperan)('juri', 'Juri Rangkap');

    ($this->simpan)($this->arenaA, ['juri_id' => [$satu->id, $satu->id, null]])
        ->assertSessionHasErrors('juri_id.1');

    expect(ArenaOfficial::where('arena_id', $this->arenaA->id)->count())->toBe(0);
});

/*
 * Kursi gelanggang berlaku sepanjang hari, jadi orang yang sama di dua
 * gelanggang berarti satu kursi PASTI kosong begitu kedua matras berjalan
 * bersamaan -- dan itu baru ketahuan di depan penonton.
 */
it('menolak orang yang sudah memegang kursi di gelanggang lain', function () {
    $juri = ($this->berperan)('juri', 'Juri Bentrok');

    ($this->simpan)($this->arenaA, ['juri_id' => [$juri->id, null, null]])->assertRedirect();

    ($this->simpan)($this->arenaB, ['juri_id' => [$juri->id, null, null]])
        ->assertSessionHasErrors('wasit_id');

    expect(ArenaOfficial::where('arena_id', $this->arenaB->id)->count())->toBe(0);
});

it('mengganti seluruh kursi saat disimpan ulang, bukan menumpuknya', function () {
    $lama = ($this->berperan)('juri', 'Juri Lama');
    $baru = ($this->berperan)('juri', 'Juri Baru');

    ($this->simpan)($this->arenaA, ['juri_id' => [$lama->id, null, null]])->assertRedirect();
    ($this->simpan)($this->arenaA, ['juri_id' => [$baru->id, null, null]])->assertRedirect();

    $kursi = ArenaOfficial::where('arena_id', $this->arenaA->id)->get();

    expect($kursi)->toHaveCount(1)
        ->and($kursi->first()->user_id)->toBe($baru->id);
});

it('menuntut penugasan-aparat.assign, bukan sekadar bisa melihat gelanggang', function () {
    $juri = ($this->berperan)('juri', 'Juri Biasa');

    $this->actingAs($juri)
        ->post(route('admin.turnamen.gelanggang.aparat', [$this->tournament, $this->arenaA]), [])
        ->assertForbidden();
});

/*
 * Layarnya sendiri: tombol yang tidak tergambar sama saja dengan fitur yang
 * tidak ada -- itu pelajaran dari `bagan.print` dan dari antrean Jurus tanpa
 * "Pindahkan…".
 */
it('menggambar kursi aparat di halaman Gelanggang', function () {
    $this->actingAs($this->penugas)
        ->get(route('admin.turnamen.gelanggang.index', $this->tournament))
        ->assertOk()
        ->assertSee('Aparat')
        ->assertSee('name="wasit_id"', escape: false)
        ->assertSee('name="juri_id[0]"', escape: false)
        ->assertSee('Dewan Wasit Juri');
});
