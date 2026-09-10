<?php

use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\Artisan;

/*
 * Dua sifat yang bukan soal kebijakan, jadi boleh dikunci uji.
 *
 * Siapa memiliki apa memang keputusan panitia dan berubah antar kejuaraan --
 * itu diuji terpisah, dengan daftar yang ditulis tangan
 * (IzinPenyelenggaraanTest). Yang di bawah ini bukan pilihan siapa pun:
 *
 *   1. Key yang dituntut rute atau disebut tampilan tapi tidak terdaftar di
 *      peta. ResourceGate menolaknya dan hanya menulis satu peringatan;
 *      akibatnya halaman membalas 403 atau tombolnya tidak pernah tergambar,
 *      untuk SIAPA PUN kecuali super-admin. Salah ketik satu huruf sudah
 *      cukup.
 *   2. Peran yang memiliki sebuah key sementara tiap rute yang menuntutnya
 *      juga menuntut key lain yang tidak ia punya -- kewenangan yang layarnya
 *      menolak pemiliknya. Ditemukan begitu pada Sekretariat dan
 *      `nomor-jurus.update`, 10 September 2026.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);
});

it('tidak menyisakan resource key yang tidak terdaftar di peta', function () {
    Artisan::call('silat:audit-izin', ['--json' => true]);

    $temuan = json_decode(Artisan::output(), true)['temuan'];

    expect($temuan['key_tak_dikenal_di_rute'])->toBe([])
        ->and($temuan['key_tak_dikenal_di_tampilan'])->toBe([])
        ->and($temuan['key_tanpa_permission'])->toBe([]);
});

it('tidak menyisakan peran yang memiliki kewenangan tanpa jalan mencapainya', function () {
    Artisan::call('silat:audit-izin', ['--json' => true]);

    $temuan = json_decode(Artisan::output(), true)['temuan'];

    $ringkas = array_map(
        fn (array $satu) => "{$satu['peran']} memiliki {$satu['key']} tapi tiap rutenya tertutup",
        $temuan['pemilik_tanpa_jalan'],
    );

    /*
     * Dan bentuk yang sesungguhnya menggigit: tombolnya ada, endpoint-nya
     * boleh ia tembak, tapi HALAMAN yang memuat tombol itu dijaga key lain.
     * Sekretariat vs `nomor-jurus.update` persis begitu -- dan pemeriksaan
     * yang hanya melihat rute POST tidak akan pernah melihatnya.
     */
    $tanpaLayar = array_map(
        fn (array $satu) => "{$satu['peran']} memiliki {$satu['key']}, tapi ".implode(', ', $satu['rute']).' tertutup untuknya',
        $temuan['pemilik_tanpa_layar'],
    );

    expect($ringkas)->toBe([])
        ->and($tanpaLayar)->toBe([]);
});
