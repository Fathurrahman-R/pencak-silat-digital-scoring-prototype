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
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };
});

it('memberi Sekretariat izin mencetak bagan dan jadwal', function () {
    $sekretariat = ($this->berperan)('operator-it');

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
 * Uji-uji di atas bisa saja lulus karena kebetulan; yang ini gagal tepat pada
 * keadaan yang sungguh terjadi -- nol pemilik.
 *
 * Daftarnya sengaja memuat seluruh kewenangan yang MENJALANKAN kejuaraan, bukan
 * hanya cetak: uji kotak hitam menemukan `bagan.create`, `bagan.update`,
 * `bagan.delete`, dan `nomor-jurus.update` sama-sama tanpa pemilik, sehingga
 * seluruh tahap pra-acara mustahil dijalankan siapa pun kecuali super-admin.
 */
it('memastikan kewenangan penyelenggaraan tidak berakhir tanpa pemilik satu pun', function () {
    $wajibBerpemilik = [
        ['bagan', ResourceAction::View],
        ['bagan', ResourceAction::Create],
        ['bagan', ResourceAction::Update],
        ['bagan', ResourceAction::Delete],
        ['bagan', ResourceAction::Print],
        ['jadwal', ResourceAction::View],
        ['jadwal', ResourceAction::Assign],
        ['jadwal', ResourceAction::Print],
        ['nomor-jurus', ResourceAction::Update],
    ];

    $yatim = [];

    foreach ($wajibBerpemilik as [$sumber, $aksi]) {
        $kunci = rk($sumber, $aksi);

        $ada = Role::all()->contains(function (Role $peran) use ($kunci) {
            $user = User::factory()->create();
            $user->syncRoles([$peran->name]);

            return $user->can($kunci);
        });

        if (! $ada) {
            $yatim[] = $kunci;
        }
    }

    expect($yatim)->toBe([], 'kewenangan tanpa pemilik: '.implode(', ', $yatim));
});

/*
 * Menyusun bagan dan MEMBATALKAN finalitasnya adalah dua kewenangan berbeda.
 * Operator IT menyusun; membuka kunci undian yang sudah final ada di Ketua
 * Pertandingan, yang menanggung akibatnya di gelanggang.
 */
it('memisahkan menyusun bagan dari membuka kuncinya', function () {
    $operator = ($this->berperan)('operator-it');
    $ketua = ($this->berperan)('ketua-pertandingan');

    expect($operator->can(rk('bagan', ResourceAction::Create)))->toBeTrue()
        ->and($operator->can(rk('bagan', ResourceAction::Update)))->toBeTrue()
        ->and($operator->can(rk('bagan', ResourceAction::Delete)))->toBeFalse()
        ->and($ketua->can(rk('bagan', ResourceAction::Delete)))->toBeTrue();
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
