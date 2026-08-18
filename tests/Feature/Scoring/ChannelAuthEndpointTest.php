<?php

use App\Broadcasting\ArenaChannelAuthorizer;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/*
 * Menguji otorisasi channel lewat broadcaster sungguhan, bukan dengan
 * memanggil ArenaChannelAuthorizer sebagai fungsi.
 *
 * Perbedaannya menentukan. Channel berbasis kelas punya kontrak sendiri di
 * Laravel: kelasnya wajib menyediakan method `join`. Unit test yang memanggil
 * authorizer langsung tidak pernah menyentuh kontrak itu, jadi ia tetap hijau
 * sekalipun setiap langganan presence di aplikasi sungguhan membalas 500 dan
 * tidak ada satu pun panel yang menerima siaran.
 *
 * Broadcaster bawaan lingkungan testing adalah `null`, yang meloloskan semua
 * langganan tanpa menyentuh daftar channel sama sekali — karena itu tidak
 * dipakai di sini. Driver `reverb` dipakai justru karena ia menjalankan
 * resolusi channel yang sama dengan produksi; penandatanganan presence-nya
 * murni kriptografi lokal, tidak ada satu pun sambungan jaringan yang dibuka.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->broadcaster = Broadcast::driver('reverb');
    $this->broadcaster->channel('arena.{arenaId}', ArenaChannelAuthorizer::class);

    $this->auth = function (User $user) {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'presence-arena.1',
            'socket_id' => '1234.5678',
        ]);

        $request->setUserResolver(fn () => $user);

        return $this->broadcaster->auth($request);
    };
});

/*
 * Jawaban presence yang sah berisi tanda tangan `auth` dan `channel_data`
 * berisi identitas anggota. Yang diperiksa di sini identitasnya, bukan
 * tanda tangannya — kriptografinya urusan Reverb, keanggotaannya urusan kita.
 */
function anggota(array $hasil): array
{
    return json_decode($hasil['channel_data'], associative: true);
}

it('mengizinkan operator bergabung ke presence channel gelanggang', function () {
    $operator = User::factory()->create();
    $operator->syncRoles(['operator-it']);

    expect(anggota(($this->auth)($operator)))
        ->toMatchArray(['user_id' => (string) $operator->id])
        ->and(anggota(($this->auth)($operator))['user_info'])
        ->toMatchArray(['id' => $operator->id, 'name' => $operator->name]);
});

it('mengizinkan juri bergabung ke presence channel gelanggang', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    expect(anggota(($this->auth)($juri)))->toMatchArray(['user_id' => (string) $juri->id]);
});

it('mengizinkan wasit bergabung ke presence channel gelanggang', function () {
    $wasit = User::factory()->create();
    $wasit->syncRoles(['wasit']);

    expect(anggota(($this->auth)($wasit)))->toMatchArray(['user_id' => (string) $wasit->id]);
});

it('menolak pengguna tanpa peran pertandingan', function () {
    $tanpaPeran = User::factory()->create();

    ($this->auth)($tanpaPeran);
})->throws(AccessDeniedHttpException::class);
