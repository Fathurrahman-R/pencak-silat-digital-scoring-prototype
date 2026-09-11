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
    $this->turnamen = Tournament::factory()->create();
});

function masukSebagai(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([peranSistem($role)]);

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
 * Yang dipasangkan di sini Sekretariat dan Ketua Pertandingan, karena
 * keduanya berlawanan penuh pada dua baris pra-acara: Sekretariat memegang
 * `invoice` sekaligus `timbang-badan`, Ketua Pertandingan tidak memegang
 * satu pun. Kalau penyaringnya lepas, Ketua akan ikut melihat daftar
 * tagihan dan daftar timbang yang bukan urusannya.
 *
 * Sejak Bendahara dan Petugas Timbang dilebur ke Sekretariat, tidak ada lagi
 * dua peran yang saling melengkapi satu-lawan-satu seperti dulu — pembedanya
 * kini "punya semua" lawan "tidak punya sama sekali".
 */
it('hanya menampilkan baris yang izinnya dimiliki pengguna', function () {
    $kelas = WeightClass::factory()->create(['tournament_id' => $this->turnamen->id]);

    Registration::factory()->count(2)->create([
        'weight_class_id' => $kelas->id,
        'status' => StatusPendaftaran::Terverifikasi,
    ]);

    kontingenNunggak($this->turnamen, $kelas, 'Kontingen Uji');

    masukSebagai('operator-it');
    $sekretariat = collect(pekerjaan($this->turnamen))->pluck('benda');

    masukSebagai('ketua-pertandingan');
    $ketua = collect(pekerjaan($this->turnamen))->pluck('benda');

    expect($sekretariat)->toContain('tagihan kontingen belum lunas')
        ->and($sekretariat)->toContain('pesilat belum ditimbang')
        ->and($ketua)->not->toContain('tagihan kontingen belum lunas')
        ->and($ketua)->not->toContain('pesilat belum ditimbang');
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
