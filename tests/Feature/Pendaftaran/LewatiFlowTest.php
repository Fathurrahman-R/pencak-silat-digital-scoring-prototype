<?php

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Registration;
use App\Models\RegistrationDocument;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Keuangan\InvoiceBuilder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Kebanyakan panitia memungut biaya dan memeriksa berkas di meja sekretariat,
 * dengan map kertas. Dua saklar di config/pendaftaran.php melepas paksaan itu
 * dari sistem tanpa menghapus fiturnya.
 *
 * Rangkaian uji lain berjalan dengan kedua saklar MATI (dipatok phpunit.xml),
 * jadi berkas inilah satu-satunya yang menyalakannya.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->sekretariat = User::factory()->create();
    $this->sekretariat->syncRoles(['operator-it']);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelasC = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
});

/** Pendaftaran berstatus draf, berkas atletnya sengaja dibiarkan kosong. */
function pendaftaranTanpaBerkas(Contingent $kontingen, $kelas): Registration
{
    $athlete = Athlete::factory()->for($kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);

    $registration = Registration::factory()->for($kontingen)->create(['weight_class_id' => $kelas->id]);
    $registration->athletes()->attach($athlete);

    return $registration->refresh();
}

function ajukan(Registration $pendaftaran, Tournament $tournament, Contingent $kontingen): string
{
    return "/admin/turnamen/{$tournament->id}/kontingen/{$kontingen->id}/pendaftaran/{$pendaftaran->id}/ajukan";
}

it('langsung mengesahkan pendaftaran saat verifikasi dimatikan', function () {
    config(['pendaftaran.lewati_verifikasi' => true]);

    $pendaftaran = pendaftaranTanpaBerkas($this->kontingen, $this->kelasC);

    $this->actingAs($this->admin)
        ->post(ajukan($pendaftaran, $this->tournament, $this->kontingen))
        ->assertSessionHasNoErrors();

    expect($pendaftaran->fresh())
        ->status->toBe(StatusPendaftaran::Terverifikasi)
        ->submitted_at->not->toBeNull()
        ->verified_at->not->toBeNull();
});

/*
 * Yang mengesahkan adalah setelan server, bukan manusia. Mengisi verified_by
 * dengan id penekan tombol berarti jejak audit menyebut nama orang yang tidak
 * pernah memeriksa apa pun.
 */
it('membiarkan verified_by kosong karena tidak ada yang memutuskan', function () {
    config(['pendaftaran.lewati_verifikasi' => true]);

    $pendaftaran = pendaftaranTanpaBerkas($this->kontingen, $this->kelasC);

    $this->actingAs($this->admin)->post(ajukan($pendaftaran, $this->tournament, $this->kontingen));

    expect($pendaftaran->fresh()->verified_by)->toBeNull();
});

it('tetap menahan pengajuan berkas kosong saat verifikasi menyala', function () {
    $pendaftaran = pendaftaranTanpaBerkas($this->kontingen, $this->kelasC);

    $this->actingAs($this->admin)
        ->post(ajukan($pendaftaran, $this->tournament, $this->kontingen))
        ->assertSessionHasErrors('berkas');

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Draf);
});

it('mengesahkan pendaftaran tanpa tagihan lunas saat pembayaran dimatikan', function () {
    config(['pendaftaran.lewati_pembayaran' => true]);

    $athlete = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);

    foreach ($athlete->berkasWajib($this->tournament) as $jenis) {
        RegistrationDocument::factory()->for($athlete)->create(['jenis' => $jenis]);
    }

    $pendaftaran = Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($athlete);

    $this->actingAs($this->sekretariat)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$pendaftaran->id}/setujui")
        ->assertSessionHasNoErrors();

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi);
});

/*
 * Tagihan yang dikunci membekukan pendaftaran supaya yang dibayar sama dengan
 * yang ditagih. Kejuaraan yang tidak menjadikan tagihan sebagai syarat tidak
 * punya alasan membeku.
 */
it('tidak membekukan pendaftaran saat pembayaran dimatikan', function () {
    config(['pendaftaran.lewati_pembayaran' => true]);

    $pertama = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);
    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pertama);

    $builder = new InvoiceBuilder;
    (new KelolaInvoice($builder))->kunci($builder->untuk($this->kontingen));

    $kedua = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 57.0]);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/tanding", [
            'athlete_id' => $kedua->id,
            'weight_class_id' => $this->kelasC->id,
        ])
        ->assertSessionHasNoErrors();

    expect($this->kontingen->registrations()->count())->toBe(2);
});

/*
 * Rutenya tidak boleh ikut hilang. Pendaftaran yang telanjur masuk antrean
 * sebelum saklar dinyalakan masih harus bisa diputuskan panitia.
 */
it('membiarkan menu verifikasi tetap bisa dibuka meski saklarnya menyala', function () {
    config(['pendaftaran.lewati_verifikasi' => true]);

    $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi")
        ->assertOk();
});
