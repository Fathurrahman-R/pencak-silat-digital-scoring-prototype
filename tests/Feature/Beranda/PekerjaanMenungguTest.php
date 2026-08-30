<?php

use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightClass;
use App\Support\Beranda\PekerjaanMenunggu;
use App\Support\Keuangan\InvoiceBuilder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->turnamen = Tournament::factory()->create();
});

function masukSebagai(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    test()->actingAs($user);

    return $user;
}

/** @return array<int, array<string, mixed>> */
function pekerjaan(?Tournament $turnamen): array
{
    return app(PekerjaanMenunggu::class)->untuk($turnamen);
}

/**
 * Kontingen yang punya satu tagihan belum lunas.
 *
 * Tagihan dibangun InvoiceBuilder, bukan dibuat langsung: nomornya, itemnya,
 * dan totalnya diturunkan dari pendaftaran, dan tagihan yang dirakit tangan
 * tidak akan menyerupai yang dihasilkan aplikasi.
 */
function kontingenNunggak(Tournament $turnamen, WeightClass $kelas, string $nama): Contingent
{
    $kontingen = Contingent::factory()->for($turnamen)->create(['name' => $nama]);

    $pendaftaran = Registration::factory()->for($kontingen)->create(['weight_class_id' => $kelas->id]);
    $pendaftaran->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    (new InvoiceBuilder)->untuk($kontingen);

    return $kontingen->refresh();
}

it('tidak menampilkan baris yang jumlahnya nol', function () {
    masukSebagai(config('resources.super_admin_role'));

    // Kejuaraan baru tanpa peserta: tidak ada satu pun pekerjaan yang menunggu,
    // dan daftar penuh baris bernilai nol justru menyembunyikan yang penting.
    $baris = collect(pekerjaan($this->turnamen))->pluck('benda');

    expect($baris)->not->toContain('pendaftaran menunggu diverifikasi')
        ->and($baris)->not->toContain('tagihan kontingen belum lunas');
});

it('menghitung pendaftaran yang menunggu diverifikasi', function () {
    masukSebagai(config('resources.super_admin_role'));

    $kelas = WeightClass::factory()->create(['tournament_id' => $this->turnamen->id]);

    Registration::factory()->count(3)->create([
        'weight_class_id' => $kelas->id,
        'status' => StatusPendaftaran::Diajukan,
    ]);

    Registration::factory()->create([
        'weight_class_id' => $kelas->id,
        'status' => StatusPendaftaran::Terverifikasi,
    ]);

    $baris = collect(pekerjaan($this->turnamen))
        ->firstWhere('benda', 'pendaftaran menunggu diverifikasi');

    expect($baris['jumlah'])->toBe(3);
});

it('menghitung tagihan lewat kontingennya, bukan lewat kejuaraan', function () {
    masukSebagai(config('resources.super_admin_role'));

    $kelas = WeightClass::factory()->create(['tournament_id' => $this->turnamen->id]);
    kontingenNunggak($this->turnamen, $kelas, 'Kontingen Uji');

    // Tagihan kejuaraan LAIN tidak boleh ikut terhitung.
    $turnamenLain = Tournament::factory()->create();
    $kelasLain = WeightClass::factory()->create(['tournament_id' => $turnamenLain->id]);
    kontingenNunggak($turnamenLain, $kelasLain, 'Kontingen Lain');

    $baris = collect(pekerjaan($this->turnamen))
        ->firstWhere('benda', 'tagihan kontingen belum lunas');

    expect($baris['jumlah'])->toBe(1);
});

/*
 * Inti dari "satu layar, isi menyesuaikan izin".
 *
 * Yang dipasangkan di sini Bendahara dan Petugas Timbang Badan, karena
 * keduanya saling melengkapi: Bendahara memegang `invoice` tapi tidak
 * `timbang-badan`, dan Petugas Timbang sebaliknya. Kalau penyaringnya lepas,
 * kedua-duanya akan melihat seluruh daftar.
 *
 * Yang TIDAK diuji di sini: antrean verifikasi. Keduanya sama-sama memegang
 * `pendaftaran => lihat` -- Bendahara butuh melihat pendaftaran karena
 * tagihannya dihitung dari sana, dan Petugas Timbang butuh tahu siapa yang
 * pendaftarannya sudah sah sebelum menimbangnya. Memakainya sebagai pembeda
 * akan menghasilkan uji yang gagal karena premisnya keliru, bukan karena
 * kodenya salah.
 */
it('hanya menampilkan baris yang izinnya dimiliki pengguna', function () {
    $kelas = WeightClass::factory()->create(['tournament_id' => $this->turnamen->id]);

    Registration::factory()->count(2)->create([
        'weight_class_id' => $kelas->id,
        'status' => StatusPendaftaran::Terverifikasi,
    ]);

    kontingenNunggak($this->turnamen, $kelas, 'Kontingen Uji');

    masukSebagai('bendahara');
    $bendahara = collect(pekerjaan($this->turnamen))->pluck('benda');

    masukSebagai('petugas-timbang');
    $timbang = collect(pekerjaan($this->turnamen))->pluck('benda');

    expect($bendahara)->toContain('tagihan kontingen belum lunas')
        ->and($bendahara)->not->toContain('pesilat belum ditimbang')
        ->and($timbang)->toContain('pesilat belum ditimbang')
        ->and($timbang)->not->toContain('tagihan kontingen belum lunas');
});

it('tiap baris membawa jumlah, sebab, dan tautannya', function () {
    masukSebagai(config('resources.super_admin_role'));

    $kelas = WeightClass::factory()->create(['tournament_id' => $this->turnamen->id]);
    Registration::factory()->create([
        'weight_class_id' => $kelas->id,
        'status' => StatusPendaftaran::Diajukan,
    ]);

    $baris = collect(pekerjaan($this->turnamen))
        ->firstWhere('benda', 'pendaftaran menunggu diverifikasi');

    // Angka tanpa sebab dan tanpa tujuan hanya mengulang apa yang sudah
    // dilakukan ubin metrik sebelumnya.
    expect($baris['jumlah'])->toBe(1)
        ->and($baris['sebab'])->not->toBeEmpty()
        ->and($baris['tautan'])->toContain('/verifikasi')
        ->and($baris['ikon'])->not->toBeEmpty();
});

it('tidak meledak saat belum ada kejuaraan sama sekali', function () {
    masukSebagai(config('resources.super_admin_role'));

    expect(pekerjaan(null))->toBeArray();
});
