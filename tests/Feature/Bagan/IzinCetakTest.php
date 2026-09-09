<?php

use App\Enums\ResourceAction;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Cetak bagan dan cetak jadwal harus dimiliki peran yang MEMANG mencetaknya.
 *
 * Sampai uji kotak hitam 9 September 2026, `bagan.print` dan `jadwal.print`
 * tidak dimiliki satu peran pun. Tombol "Cetak PDF" ada di kedua halaman, tapi
 * ia dibungkus @resource -- jadi ia tidak pernah tergambar untuk siapa pun,
 * dan alamatnya membalas 403. Satu-satunya yang lolos adalah super-admin,
 * lewat Gate::before, dan super-admin bukan orang yang berdiri di meja
 * sekretariat pada pagi hari-H.
 *
 * Kegagalannya diam: tombol yang disembunyikan tidak terlihat sebagai tombol
 * yang hilang. Tidak ada satu pun uji yang menangkapnya karena uji cetak yang
 * sudah ada memakai akun super-admin.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->berperan = function (string $peran): User {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };
});

it('memberi Sekretariat izin mencetak bagan dan jadwal', function () {
    $sekretariat = ($this->berperan)('sekretariat');

    expect($sekretariat->can(rk('bagan', ResourceAction::Print)))->toBeTrue()
        ->and($sekretariat->can(rk('jadwal', ResourceAction::Print)))->toBeTrue();
});

it('memberi Ketua Pertandingan izin mencetak bagan dan jadwal', function () {
    $ketua = ($this->berperan)('ketua-pertandingan');

    expect($ketua->can(rk('bagan', ResourceAction::Print)))->toBeTrue()
        ->and($ketua->can(rk('jadwal', ResourceAction::Print)))->toBeTrue();
});

/*
 * Yang menentukan: SEKURANG-KURANGNYA satu peran operasional memilikinya.
 * Uji dua di atas bisa saja lulus karena kebetulan; yang ini gagal tepat pada
 * keadaan yang sungguh terjadi -- nol pemilik.
 */
it('memastikan izin cetak tidak berakhir tanpa pemilik satu pun', function () {
    foreach (['bagan', 'jadwal'] as $sumber) {
        $kunci = rk($sumber, ResourceAction::Print);

        $pemilik = Role::all()->filter(function (Role $peran) use ($kunci) {
            $user = User::factory()->create();
            $user->syncRoles([$peran->name]);

            return $user->can($kunci);
        });

        expect($pemilik)->not->toBeEmpty("tidak ada satu peran pun yang memiliki {$kunci}");
    }
});

/*
 * Official kontingen boleh MELIHAT bagan, tapi tidak menerbitkan cetakannya:
 * lembar yang dipaku di papan pengumuman terbit dari meja panitia, bukan dari
 * kontingen peserta.
 */
it('tidak memberi official kontingen izin mencetak', function () {
    $official = ($this->berperan)('official-kontingen');

    expect($official->can(rk('bagan', ResourceAction::View)))->toBeTrue()
        ->and($official->can(rk('bagan', ResourceAction::Print)))->toBeFalse();
});
