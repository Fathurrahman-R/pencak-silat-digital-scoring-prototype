<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Keuangan\InvoiceBuilder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Tombol "Kunci tagihan dan lanjut bayar" pernah tidak pernah tampil untuk
 * SIAPA PUN -- termasuk super-admin.
 *
 * Sebabnya bukan wewenang: halaman menaruh tombolnya di <x-slot:footer>,
 * sedangkan <x-si.kartu> waktu itu tidak punya slot bernama `footer`. Blade
 * membuang isinya tanpa satu pun galat, dan rantai pra-acara terputus di situ
 * -- pendaftaran kontingen baru tidak pernah bisa mencapai Menunggu
 * Pembayaran, apalagi Terverifikasi. Ditemukan lewat pengujian blackbox, bukan
 * lewat rangkaian uji: seluruh tagihan yang ada dibuat penyemai, tidak ada
 * satu pun yang pernah melewati layar ini.
 */
beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $kelas->id]);
    $pendaftaran->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

    $this->invoice = (new InvoiceBuilder)->untuk($this->kontingen);

    $this->official = User::factory()->create();
    $this->official->syncRoles(['official-kontingen']);
    $this->kontingen->update(['user_id' => $this->official->id]);
});

it('menampilkan tombol kunci tagihan pada tagihan yang masih draf', function () {
    $this->actingAs($this->official)
        ->get(route('admin.turnamen.kontingen.tagihan.show', [$this->tournament, $this->kontingen]))
        ->assertOk()
        ->assertSee('Kunci tagihan dan lanjut bayar');
});

/*
 * Official mengunci tagihannya sendiri: panduan alur menaruh langkah itu di
 * kursinya ("Tagihan -> Kunci tagihan dan lanjut bayar"), dan tanpa wewenang
 * `invoice.update` rantainya berhenti di draf.
 */
it('mengizinkan official kontingen mengunci tagihannya sendiri', function () {
    $this->actingAs($this->official)
        ->post(route('admin.turnamen.kontingen.tagihan.kunci', [$this->tournament, $this->kontingen]))
        ->assertRedirect();

    expect($this->invoice->fresh()->status->value)->not->toBe('draf');
});

/*
 * Menandai lunas tetap milik Sekretariat. Kontingen yang bisa menyatakan
 * tagihannya sendiri lunas membuat seluruh verifikasi kehilangan artinya.
 */
it('menolak official kontingen menandai tagihannya sendiri lunas', function () {
    $this->actingAs($this->official)
        ->post(route('admin.turnamen.bendahara.lunas', [$this->tournament, $this->invoice]))
        ->assertForbidden();
});
