<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\KategoriPertandingan;
use App\Enums\StatusInvoice;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelasC = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
});

function pendaftaranTanding(Contingent $kontingen, $kelas): Registration
{
    $registration = Registration::factory()->for($kontingen)->create(['weight_class_id' => $kelas->id]);
    $registration->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    return $registration;
}

it('menyimpan tarif per nomor', function () {
    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/tarif", [
            'kategori' => KategoriPertandingan::Tanding->value,
            'golongan_usia' => GolonganUsia::Dewasa->value,
            'amount' => 225_000,
        ])
        ->assertSessionHasNoErrors();

    expect($this->tournament->feeSchedules()->count())->toBe(1);
});

/*
 * Satu kombinasi kategori dan golongan hanya boleh punya satu tarif. Memasukkan
 * kombinasi yang sama berarti mengoreksi angkanya, bukan menambah baris kembar
 * yang lalu harus dipilih salah satunya secara sewenang-wenang.
 */
it('mengoreksi tarif yang sudah ada alih-alih menggandakannya', function () {
    $url = "/admin/turnamen/{$this->tournament->id}/tarif";
    $isi = ['kategori' => KategoriPertandingan::Tanding->value, 'golongan_usia' => '', 'amount' => 150_000];

    $this->actingAs($this->admin)->post($url, $isi);
    $this->actingAs($this->admin)->post($url, [...$isi, 'amount' => 175_000]);

    expect($this->tournament->feeSchedules()->count())->toBe(1)
        ->and($this->tournament->feeSchedules()->first()->amount)->toBe(175_000);
});

/*
 * Mengubah tarif setelah kejuaraan berjalan berarti dua kontingen membayar
 * harga berbeda untuk nomor yang sama, dan yang membayar lebih dulu tidak punya
 * cara mengetahuinya.
 */
it('mengunci tarif setelah kejuaraan berjalan', function () {
    $this->tournament->update(['status' => 'berjalan']);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/tarif", ['amount' => 100_000])
        ->assertForbidden();
});

it('menampilkan tagihan yang tersusun dari pendaftaran', function () {
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);
    pendaftaranTanding($this->kontingen, $this->kelasC);

    $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/tagihan")
        ->assertOk()
        ->assertSee('Rp 150.000');
});

it('mengunci tagihan lalu mencairkannya kembali', function () {
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);
    pendaftaranTanding($this->kontingen, $this->kelasC);

    $dasar = "/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/tagihan";

    $this->actingAs($this->admin)->post("{$dasar}/kunci")->assertSessionHasNoErrors();
    expect($this->kontingen->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);

    $this->actingAs($this->admin)->post("{$dasar}/batal")->assertSessionHasNoErrors();
    expect($this->kontingen->invoice->fresh()->status)->toBe(StatusInvoice::Draf);
});

it('menolak mengunci tagihan yang belum ada tarifnya', function () {
    pendaftaranTanding($this->kontingen, $this->kelasC);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/tagihan/kunci")
        ->assertSessionHasErrors('invoice');
});

it('menutup tagihan kontingen lain dari official', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    $this->actingAs($official)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/tagihan")
        ->assertNotFound();
});
