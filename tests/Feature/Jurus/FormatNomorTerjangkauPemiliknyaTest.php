<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\ResourceAction;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Spatie\Permission\Models\Role;

/*
 * Kewenangan yang pemiliknya tidak bisa mencapai layarnya.
 *
 * Uji kotak hitam 10 September 2026 menemukan lapisan ketiga dari keluarga
 * cacat yang sama. Yang pertama: kewenangan tanpa pemilik (`bagan.print` dan
 * kawan-kawan). Yang kedua: kewenangan berpemilik tanpa tombol (antrean Jurus
 * tanpa "Pindahkan…"). Yang ini: kewenangan berpemilik, bertombol, tapi
 * layarnya membalas 403 kepada pemiliknya sendiri.
 *
 * `nomor-jurus.update` -- pemilih format battle/peringkat -- hanya dimiliki
 * Sekretariat. Pemilih itu berdiri di halaman daftar nomor Jurus, yang dijaga
 * `penampilan-jurus.view`: kewenangan yang justru TIDAK dimiliki Sekretariat.
 * Jadi satu-satunya orang yang boleh menetapkan format nomor tidak bisa
 * membuka layar yang menetapkannya, dan menunya pun tidak tergambar untuknya.
 *
 * Seluruh uji yang sudah ada lolos karena memakai super-admin, yang melewati
 * pemeriksaan lewat Gate::before.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    // Nomor Jurus lahir dari naskah 2025, dan tanpa satu pun nomor halaman
    // ini kosong -- pemilih formatnya memang tidak tergambar, dan ujinya
    // lulus atau gagal karena alasan yang salah.
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->sebagai = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };
});

/*
 * Pernyataan yang sesungguhnya: tiap kewenangan harus punya JALAN, bukan cuma
 * pemilik. Ditulis sebagai aturan, bukan sebagai satu kasus, supaya penjaga
 * halaman yang diganti nanti tetap tertangkap.
 */
it('membiarkan pemilik nomor-jurus.update membuka layar yang memuat pemilih format', function () {
    $pemilik = Role::all()->filter(fn (Role $r) => $r->hasPermissionTo(rk('nomor-jurus', ResourceAction::Update)));

    expect($pemilik)->not->toBeEmpty('nomor-jurus.update tidak dimiliki peran mana pun');

    foreach ($pemilik as $peran) {
        $this->actingAs(($this->sebagai)($peran->name))
            ->get(route('admin.turnamen.jurus.nomor', $this->tournament))
            ->assertOk();
    }
});

it('menggambar pemilih format untuk Sekretariat, di layar yang bisa ia buka', function () {
    $this->actingAs(($this->sebagai)('sekretariat'))
        ->get(route('admin.turnamen.jurus.nomor', $this->tournament))
        ->assertOk()
        ->assertSee('name="format"', escape: false);
});

/*
 * Layar yang terbuka tanpa menu yang menuntun ke sana sama saja dengan
 * tertutup: tidak ada satu pun tautan ke halaman ini di luar remah-remah
 * halaman bagan.
 */
it('menampilkan menu Kategori Jurus untuk Sekretariat', function () {
    $menu = $this->actingAs(($this->sebagai)('sekretariat'))
        ->get(route('dashboard'))
        ->assertOk();

    $menu->assertSee('Kategori Jurus');
});

/*
 * Yang dibuka hanya pintunya, bukan seluruh rumah: Sekretariat tetap tidak
 * memegang penampilan, jadi ia tidak diberi tombol menuju layar yang akan
 * menolaknya.
 */
it('tidak menawarkan "Kelola penampilan" kepada yang tidak memegang penampilan-jurus', function () {
    $this->actingAs(($this->sebagai)('sekretariat'))
        ->get(route('admin.turnamen.jurus.nomor', $this->tournament))
        ->assertOk()
        ->assertDontSee('Kelola penampilan');

    $this->actingAs(($this->sebagai)('ketua-pertandingan'))
        ->get(route('admin.turnamen.jurus.nomor', $this->tournament))
        ->assertOk()
        ->assertSee('Kelola penampilan');
});

it('tetap menolak Sekretariat di layar penampilan satu nomor', function () {
    $nomor = $this->tournament->jurusEvents()->first();

    if ($nomor === null) {
        $this->markTestSkipped('kejuaraan uji tidak menyusun nomor Jurus');
    }

    $this->actingAs(($this->sebagai)('sekretariat'))
        ->get(route('admin.turnamen.jurus.index', [$this->tournament, $nomor]))
        ->assertForbidden();
});
