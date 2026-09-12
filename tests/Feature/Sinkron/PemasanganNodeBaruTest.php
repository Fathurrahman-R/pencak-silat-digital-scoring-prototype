<?php

use App\Models\Contingent;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\PembungkusPaket;
use App\Support\Sinkron\PetaSinkron;
use Illuminate\Support\Facades\DB;

/*
 * Lima cacat yang ditemukan uji kotak hitam multi-node, 11 September 2026.
 *
 * Semuanya berkumpul di satu tempat: laptop gelanggang yang baru dipasang.
 * Dokumen menyebut alurnya -- migrasi tanpa seed, buka `/pemasangan`, tarik
 * dari node global -- dan alur itu tidak pernah bisa diselesaikan sekali pun.
 */

beforeEach(function () {
    config()->set('sinkron.peran', 'global');
    config()->set('sinkron.node', 'global');
    config()->set('sinkron.arena', '');

    $this->tournament = Tournament::factory()->create();
});

/*
 * Catatan sinkron lahir dari observer, jadi ia hanya berisi baris yang BERUBAH
 * sesudah observernya terpasang. Kejuaraan yang datanya sudah tersusun punya
 * catatan yang nyaris kosong, dan node baru menerima anak tanpa induknya.
 */
it('menyemai catatan dari keadaan sekarang, termasuk baris yang lahir sebelum catatannya ada', function () {
    User::factory()->create();

    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();

    expect(DB::table('sinkron_keluar')->count())->toBe(0);

    $jumlah = app(CatatanKeluar::class)->semai();

    expect($jumlah)->toBeGreaterThan(0);

    $tabel = DB::table('sinkron_keluar')->distinct()->pluck('tabel');

    expect($tabel)->toContain('tournaments')
        ->and($tabel)->toContain('users')
        ->and($tabel)->toContain('roles');
});

/*
 * Tiga tabel pivot Spatie tidak punya kolom `id`, sementara seluruh mesin
 * sinkron menulis dan membaca lewat `id`. Akibatnya akun tiba di node
 * gelanggang tanpa satu pun peran, lalu tiap panel membalas "Akses ditolak" --
 * pesan yang menyuruh orang mencari kesalahan di tempat yang salah.
 */
it('ikut menyemai pivot peran yang tidak punya kolom id', function () {
    $user = User::factory()->create();
    $user->syncRoles(['ketua-pertandingan']);

    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();

    app(CatatanKeluar::class)->semai();

    $pivot = DB::table('sinkron_keluar')->where('tabel', 'model_has_roles')->pluck('baris_id');

    expect($pivot)->not->toBeEmpty();

    // Penandanya harus bisa dikembalikan jadi klausa `where` yang menemukan
    // barisnya lagi -- itulah satu-satunya gunanya.
    $klausa = PetaSinkron::klausaKunci('model_has_roles', $pivot->first());

    expect(DB::table('model_has_roles')->where($klausa)->exists())->toBeTrue();
});

it('membawa pivot peran di dalam paket, lengkap dengan isinya', function () {
    $user = User::factory()->create();
    $user->syncRoles(['juri']);

    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();
    app(CatatanKeluar::class)->semai();

    $paket = app(PembungkusPaket::class)->bangun(0, 5000);

    $pivot = collect($paket['baris'])->where('tabel', 'model_has_roles');

    expect($pivot)->not->toBeEmpty()
        ->and($pivot->first())->toHaveKey('data')
        ->and($pivot->first()['data'])->toHaveKey('role_id');
});

/*
 * Peer yang menarik dari nol adalah peer yang belum punya apa-apa. Node yang
 * belum pernah menyemai menyemai saat itu juga -- kalau tidak, laptop yang
 * dipasang panitia tidak akan pernah bisa selesai menarik, dan pesannya tidak
 * menyebut sebabnya.
 */
it('menyemai sendiri saat peer pertama menarik dari nol', function () {
    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();

    $paket = app(PembungkusPaket::class)->bangun(0);

    expect($paket['baris'])->not->toBeEmpty()
        ->and(app(CatatanKeluar::class)->sudahDisemai())->toBeTrue();
});

it('tidak menyemai dua kali', function () {
    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();

    app(PembungkusPaket::class)->bangun(0);
    $sesudahPertama = DB::table('sinkron_keluar')->count();

    app(PembungkusPaket::class)->bangun(0);

    expect(DB::table('sinkron_keluar')->count())->toBe($sesudahPertama);
});

/*
 * Induk sebelum anak, dan itu yang membuat potongan pertama pun bisa
 * diterapkan tanpa melanggar foreign key.
 */
it('menyemai dengan urutan yang aman terhadap foreign key', function () {
    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();

    app(CatatanKeluar::class)->semai();

    $urutan = array_flip(PetaSinkron::urutanTerapkan());
    $terlihat = DB::table('sinkron_keluar')->orderBy('id')->pluck('tabel');

    $posisiTerbesar = -1;

    foreach ($terlihat as $tabel) {
        $posisi = $urutan[$tabel] ?? PHP_INT_MAX;

        expect($posisi)->toBeGreaterThanOrEqual($posisiTerbesar, "tabel {$tabel} disemai lebih dulu dari induknya");

        $posisiTerbesar = max($posisiTerbesar, $posisi);
    }
});

/*
 * Perubahan peran SESUDAH penyemaian: pivot tidak punya model, jadi tidak
 * pernah lewat observer. Yang menangkapnya listener event Spatie.
 */
it('mencatat perubahan peran yang terjadi sesudah penyemaian', function () {
    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();
    app(CatatanKeluar::class)->semai();

    $sebelum = DB::table('sinkron_keluar')->where('tabel', 'model_has_roles')->count();

    $user = User::factory()->create();
    $user->assignRole('juri');

    expect(DB::table('sinkron_keluar')->where('tabel', 'model_has_roles')->count())
        ->toBeGreaterThan($sebelum);
});

/*
 * Pendaftaran yang lahir sesudah laptop gelanggang terpasang. Barisnya
 * tercatat lewat observer, tapi atletnya menempel lewat pivot -- dan pivot
 * tanpa model tidak pernah terdengar. Yang tiba di gelanggang: pendaftaran
 * tanpa satu atlet pun, sudut partai tanpa nama.
 */
it('mencatat atlet yang ditempelkan ke pendaftaran sesudah penyemaian', function () {
    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();
    app(CatatanKeluar::class)->semai();

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $pendaftaran = App\Models\Registration::factory()->for($kontingen)->create();
    $atlet = App\Models\Athlete::factory()->for($kontingen)->create();

    $sebelum = DB::table('sinkron_keluar')->max('id');

    $pendaftaran->athletes()->attach($atlet, ['position' => 1]);

    $pivotId = DB::table('registration_athlete')
        ->where('registration_id', $pendaftaran->id)
        ->where('athlete_id', $atlet->id)
        ->value('id');

    $tercatat = DB::table('sinkron_keluar')
        ->where('id', '>', $sebelum)
        ->where('tabel', 'registration_athlete')
        ->pluck('baris_id');

    expect($tercatat->all())->toBe([(string) $pivotId]);
});

/*
 * Mencabut atlet dari pendaftaran. `detach()` menyusun baris pivotnya dari dua
 * kolom kunci saja, tanpa id -- dan catatan sinkron menunjuk baris lewat id.
 * Tanpa penjagaan, perintah hapusnya tercatat menunjuk ke kosong, dan atlet
 * yang sudah dicabut tetap berdiri di daftar gelanggang.
 */
it('mencatat atlet yang dicabut dari pendaftaran, lengkap dengan penandanya', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $pendaftaran = App\Models\Registration::factory()->for($kontingen)->create();
    $atlet = App\Models\Athlete::factory()->for($kontingen)->create();
    $pendaftaran->athletes()->attach($atlet, ['position' => 1]);

    $pivotId = (string) DB::table('registration_athlete')
        ->where('registration_id', $pendaftaran->id)
        ->where('athlete_id', $atlet->id)
        ->value('id');

    DB::table('sinkron_keluar')->delete();
    DB::table('sinkron_kursor')->delete();
    app(CatatanKeluar::class)->semai();

    $sebelum = DB::table('sinkron_keluar')->max('id');

    $pendaftaran->athletes()->detach($atlet);

    $catatan = DB::table('sinkron_keluar')
        ->where('id', '>', $sebelum)
        ->where('tabel', 'registration_athlete')
        ->get(['baris_id', 'aksi']);

    expect($catatan)->toHaveCount(1)
        ->and($catatan->first()->aksi)->toBe(CatatanKeluar::HAPUS)
        ->and($catatan->first()->baris_id)->toBe($pivotId);
});

/*
 * Node gelanggang yang menulis data kejuaraan menghasilkan data hantu: hidup
 * di satu laptop, tidak pernah tercatat untuk dikirim, dan id-nya akan
 * bertabrakan dengan baris yang kelak lahir di node global.
 */
it('menolak node gelanggang menulis data kejuaraan', function () {
    config()->set('sinkron.jaga_penulis_global', true);
    config()->set('sinkron.peran', 'gelanggang');
    config()->set('sinkron.node', 'gelanggang-b');
    config()->set('sinkron.arena', 'B');
    config()->set('sinkron.peer', [['nama' => 'global', 'url' => 'http://contoh', 'token' => 'x']]);

    expect(fn () => Contingent::factory()->for($this->tournament)->create())
        ->toThrow(App\Exceptions\PenulisanDataKejuaraanDitolak::class, 'tidak boleh membuat data kejuaraan');
});

/*
 * Tapi mesin yang berdiri sendiri -- satu laptop, tanpa peer -- adalah
 * satu-satunya mesin yang ada. Menjaganya berarti melumpuhkan seluruh
 * administrasi kejuaraan demi konflik yang tidak mungkin terjadi.
 */
it('membiarkan pemasangan satu laptop menulis apa saja', function () {
    config()->set('sinkron.jaga_penulis_global', true);
    config()->set('sinkron.peran', 'gelanggang');
    config()->set('sinkron.arena', 'B');
    config()->set('sinkron.peer', []);

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    expect($kontingen->exists)->toBeTrue();
});

/*
 * Masuk ke aplikasi memperbarui baris `users`. Menolaknya berarti tidak ada
 * yang bisa masuk ke node gelanggang sama sekali -- penjagaan yang mengunci
 * pintunya sendiri.
 */
it('tetap membiarkan node gelanggang memperbarui kolom teknis akun', function () {
    $user = User::factory()->create();

    config()->set('sinkron.jaga_penulis_global', true);
    config()->set('sinkron.peran', 'gelanggang');
    config()->set('sinkron.arena', 'B');
    config()->set('sinkron.peer', [['nama' => 'global', 'url' => 'http://contoh', 'token' => 'x']]);

    $user->forceFill(['remember_token' => 'abc123'])->save();

    expect($user->fresh()->remember_token)->toBe('abc123');
});
