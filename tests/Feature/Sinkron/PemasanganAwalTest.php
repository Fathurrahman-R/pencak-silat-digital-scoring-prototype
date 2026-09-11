<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\Sinkron\PenarikPeer;

/*
 * Penarikan pertama sebuah node, saat basis datanya masih kosong.
 *
 * # Telur dan ayam
 *
 * Node gelanggang yang baru dipasang tidak punya satu pun pengguna: seluruh
 * akun panitia lahir di node global dan datang lewat sinkron. Sementara itu
 * satu-satunya jalan menarik data adalah tombol di halaman admin, yang
 * menuntut login. Node baru karena itu tidak bisa menarik apa pun sampai
 * seseorang membuat akun lokal dengan tangan lewat tinker -- akun yang, pada
 * penarikan pertama, langsung tertimpa akun bernomor sama dari node global.
 *
 * Berkas ini menguji jalan keluarnya: satu permukaan pemasangan yang terbuka
 * TANPA login, hanya selama basis datanya benar-benar kosong.
 *
 * # Kenapa ini tidak melonggarkan keamanan
 *
 *   - Ia hanya hidup saat tabel `users` kosong. Penarikan pertama membawa akun
 *     dari node global, dan sejak baris pertama itu masuk, permukaannya
 *     menghilang -- 404, bukan 403, supaya node yang sudah terpasang tidak
 *     mengumumkan bahwa jalan ini pernah ada.
 *   - Ia menuntut token sinkron sudah terpasang. Laptop yang belum
 *     dikonfigurasi tidak menyajikan apa pun ke jaringan gelanggang, dan itu
 *     jaringan yang sama dengan perangkat penonton.
 *   - Yang bisa dilakukan penyusup di jaringan itu cuma memicu penarikan dari
 *     peer yang sudah tertulis di `.env` mesin ini sendiri, memakai token yang
 *     sudah tertulis di situ juga. Ia tidak bisa memilih sumbernya, tidak bisa
 *     menyisipkan data, dan tidak ada yang bisa dibaca dari basis data yang
 *     masih kosong.
 */

beforeEach(function () {
    config([
        'sinkron.token' => 'rahasia-uji',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.peer' => [
            ['nama' => 'global', 'url' => 'http://192.168.1.10:8000', 'token' => 'rahasia-uji'],
        ],
    ]);
});

it('membuka halaman pemasangan tanpa login saat basis data masih kosong', function () {
    expect(User::query()->exists())->toBeFalse();

    $this->get(route('pemasangan.index'))
        ->assertOk()
        ->assertSee('Pemasangan node')
        ->assertSee('global');
});

it('menarik potongan pertama tanpa login', function () {
    $this->mock(PenarikPeer::class)
        ->shouldReceive('tarikSatuPotongan')
        ->once()
        ->with('global')
        ->andReturn(['diterapkan' => 12, 'selesai' => false, 'kursor' => 12]);

    $this->postJson(route('pemasangan.tarik'), ['peer' => 'global'])
        ->assertOk()
        ->assertJsonPath('diterapkan', 12);
});

/*
 * Begitu satu pengguna ada -- dan penarikan pertama pasti membawanya --
 * permukaan ini tertutup untuk selamanya.
 */
/*
 * Syaratnya bukan "sudah ada pengguna", melainkan "sudah ada yang bisa
 * MENYELESAIKAN pemasangannya sendiri".
 *
 * Penarikan awal butuh belasan potongan; akun tiba di potongan pertama dan
 * perannya menyusul beberapa potongan kemudian. Menutup halaman ini pada akun
 * pertama meninggalkan node di jalan buntu -- halaman pemasangan sudah 404,
 * halaman sinkron menuntut izin yang belum tiba, dan tidak ada seorang pun
 * yang bisa masuk untuk melanjutkan. Terlihat begitu di peramban, 11
 * September 2026: 26 akun masuk, nol peran, penarikan berhenti seperempat
 * jalan.
 */
it('tetap terbuka selama akun yang masuk belum memegang izin sinkron', function () {
    User::factory()->create();

    $this->get(route('pemasangan.index'))->assertOk();
});

/*
 * Akun berizin pun belum cukup.
 *
 * Penyemaian menaruh akun beserta perannya di potongan PERTAMA, jadi ukuran
 * "sudah ada yang bisa meneruskan" membuat halaman ini menutup diri setelah
 * seratus milidetik -- sementara ribuan baris sisanya belum berangkat dan
 * tidak ada lagi permukaan untuk menariknya. Terukur begitu, 11 September
 * 2026: akun ada, kejuaraannya tidak.
 */
it('tetap terbuka selama peer belum pernah ditarik sampai habis', function () {
    $pengguna = User::factory()->create();
    $pengguna->syncRoles(['ketua-pertandingan']);

    DB::table('sinkron_kursor')->insert([
        'peer' => 'global',
        'kursor_terakhir' => 500,
        'ditarik_pada' => now(),
        'selesai_pada' => null,
        'baris_diterapkan' => 500,
    ]);

    $this->get(route('pemasangan.index'))->assertOk();
});

it('menutup diri sesudah penarikan awal tuntas dan ada yang bisa meneruskan', function () {
    $pengguna = User::factory()->create();
    $pengguna->syncRoles(['ketua-pertandingan']);

    DB::table('sinkron_kursor')->insert([
        'peer' => 'global',
        'kursor_terakhir' => 4247,
        'ditarik_pada' => now(),
        'selesai_pada' => now(),
        'baris_diterapkan' => 4247,
    ]);

    $this->get(route('pemasangan.index'))->assertNotFound();
    $this->postJson(route('pemasangan.tarik'), ['peer' => 'global'])->assertNotFound();
});

it('tetap tertutup kalau token sinkron belum dipasang', function () {
    config(['sinkron.token' => '']);

    $this->get(route('pemasangan.index'))->assertNotFound();
});

/*
 * Node tanpa peer tidak punya siapa pun untuk ditarik. Membuka halamannya
 * hanya menawarkan tombol yang tidak bisa ditekan, dan permukaan yang terbuka
 * tanpa login sebaiknya tidak ada sedetik pun lebih lama daripada gunanya.
 */
it('tetap tertutup kalau belum ada peer terdaftar', function () {
    config(['sinkron.peer' => []]);

    $this->get(route('pemasangan.index'))->assertNotFound();
});

it('menolak peer yang tidak terdaftar di node ini', function () {
    $this->postJson(route('pemasangan.tarik'), ['peer' => 'gelanggang-tetangga'])
        ->assertStatus(422);
});
