<?php

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\AuditLog;
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

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->sekretaris = User::factory()->create();
    $this->sekretaris->syncRoles(['sekretaris-pertandingan']);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelasC = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
});

/** Pendaftaran diajukan, berkasnya lengkap. */
function pendaftaranSiap(Contingent $kontingen, $kelas, Tournament $tournament): Registration
{
    $athlete = Athlete::factory()->for($kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);

    foreach ($athlete->berkasWajib($tournament) as $jenis) {
        RegistrationDocument::factory()->for($athlete)->create(['jenis' => $jenis]);
    }

    $registration = Registration::factory()->for($kontingen)->diajukan()
        ->create(['weight_class_id' => $kelas->id]);
    $registration->athletes()->attach($athlete);

    return $registration->refresh();
}

function lunasiKontingen(Contingent $kontingen): void
{
    $builder = new InvoiceBuilder;
    $kelola = new KelolaInvoice($builder);
    $kelola->tandaiLunas($kelola->kunci($builder->untuk($kontingen)), 'manual');
}

it('mengesahkan pendaftaran yang berkasnya lengkap dan tagihannya lunas', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    lunasiKontingen($this->kontingen);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($registration->verified_by)->toBe($this->sekretaris->id)
        ->and($registration->verified_at)->not->toBeNull();

    expect(AuditLog::where('action', 'pendaftaran.verifikasi')->count())->toBe(1);
});

/*
 * Ini satu-satunya hal yang memaksa pembayaran benar-benar terjadi. Kalau
 * verifikasi bisa jalan tanpanya, tagihan hanya jadi catatan yang boleh
 * diabaikan sampai kejuaraan usai.
 */
it('menolak mengesahkan pendaftaran saat tagihan belum lunas', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasErrors('verifikasi');

    expect($registration->fresh()->status)->toBe(StatusPendaftaran::Diajukan);
});

it('menolak mengesahkan pendaftaran saat berkas wajib belum lengkap', function () {
    $athlete = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))->create();

    $registration = Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $registration->athletes()->attach($athlete);

    lunasiKontingen($this->kontingen);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasErrors('verifikasi');

    expect($registration->fresh()->status)->toBe(StatusPendaftaran::Diajukan);
});

it('menolak pendaftaran beserta alasan yang tercatat', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/tolak", [
            'rejection_reason' => 'Surat keterangan sehat terbit lebih dari satu minggu sebelum pertandingan.',
        ])
        ->assertSessionHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(StatusPendaftaran::Ditolak)
        ->and($registration->rejection_reason)->toContain('satu minggu');

    expect(AuditLog::where('action', 'pendaftaran.tolak')->first()->properties['alasan'])
        ->toContain('satu minggu');
});

/*
 * Alasan yang terlalu pendek tidak bisa ditindaklanjuti official. "Kurang"
 * atau "salah" hanya memindahkan pekerjaan menebak ke pihak lain.
 */
it('menolak alasan penolakan yang terlalu pendek', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/tolak", [
            'rejection_reason' => 'kurang',
        ])
        ->assertSessionHasErrors('rejection_reason');

    expect($registration->fresh()->status)->toBe(StatusPendaftaran::Diajukan);
});

it('menolak memutus pendaftaran yang belum diajukan', function () {
    $registration = Registration::factory()->for($this->kontingen)
        ->create(['weight_class_id' => $this->kelasC->id]);

    lunasiKontingen($this->kontingen);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasErrors('verifikasi');
});

/*
 * Panitia keliru memutus adalah hal yang terjadi. Memperbaikinya harus
 * meninggalkan jejak, bukan tampak seolah keputusan pertama tidak pernah ada.
 */
it('mengembalikan pendaftaran ke antrean beserta jejaknya', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    lunasiKontingen($this->kontingen);

    $dasar = "/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}";

    $this->actingAs($this->sekretaris)->post("{$dasar}/setujui");
    $this->actingAs($this->sekretaris)->post("{$dasar}/tinjau-ulang")->assertSessionHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(StatusPendaftaran::Diajukan)
        ->and($registration->verified_at)->toBeNull();

    expect(AuditLog::whereIn('action', ['pendaftaran.verifikasi', 'pendaftaran.tinjau_ulang'])->count())
        ->toBe(2);
});

it('membersihkan alasan penolakan saat pendaftaran akhirnya disahkan', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    lunasiKontingen($this->kontingen);

    $dasar = "/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}";

    $this->actingAs($this->sekretaris)->post("{$dasar}/tolak", [
        'rejection_reason' => 'Foto atlet tidak terbaca, mohon unggah ulang.',
    ]);
    $this->actingAs($this->sekretaris)->post("{$dasar}/tinjau-ulang");
    $this->actingAs($this->sekretaris)->post("{$dasar}/setujui");

    expect($registration->fresh())
        ->status->toBe(StatusPendaftaran::Terverifikasi)
        ->rejection_reason->toBeNull();
});

it('menolak pendaftaran kejuaraan lain lewat alamat yang ditukar', function () {
    $lain = Tournament::factory()->create();
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretaris)
        ->post("/admin/turnamen/{$lain->id}/verifikasi/{$registration->id}/setujui")
        ->assertNotFound();
});

it('menutup panel verifikasi dari official kontingen', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($official)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertForbidden();
});

it('menampilkan antrean verifikasi kepada sekretaris pertandingan', function () {
    pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretaris)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi")
        ->assertOk()
        ->assertSee($this->kontingen->name)
        // Sebab belum bisa disahkan ditulis di barisnya. Tombol mati tanpa
        // keterangan membuat panitia menebak, lalu menelepon official yang
        // juga tidak tahu.
        ->assertSee('Tagihan belum terbit');
});
