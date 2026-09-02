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

    $this->sekretariat = User::factory()->create();
    $this->sekretariat->syncRoles(['sekretariat']);

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

    $this->actingAs($this->sekretariat)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($registration->verified_by)->toBe($this->sekretariat->id)
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

    $this->actingAs($this->sekretariat)
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

    $this->actingAs($this->sekretariat)
        ->post("/admin/turnamen/{$this->tournament->id}/verifikasi/{$registration->id}/setujui")
        ->assertSessionHasErrors('verifikasi');

    expect($registration->fresh()->status)->toBe(StatusPendaftaran::Diajukan);
});

it('menolak pendaftaran beserta alasan yang tercatat', function () {
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretariat)
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

    $this->actingAs($this->sekretariat)
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

    $this->actingAs($this->sekretariat)
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

    $this->actingAs($this->sekretariat)->post("{$dasar}/setujui");
    $this->actingAs($this->sekretariat)->post("{$dasar}/tinjau-ulang")->assertSessionHasNoErrors();

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

    $this->actingAs($this->sekretariat)->post("{$dasar}/tolak", [
        'rejection_reason' => 'Foto atlet tidak terbaca, mohon unggah ulang.',
    ]);
    $this->actingAs($this->sekretariat)->post("{$dasar}/tinjau-ulang");
    $this->actingAs($this->sekretariat)->post("{$dasar}/setujui");

    expect($registration->fresh())
        ->status->toBe(StatusPendaftaran::Terverifikasi)
        ->rejection_reason->toBeNull();
});

it('menolak pendaftaran kejuaraan lain lewat alamat yang ditukar', function () {
    $lain = Tournament::factory()->create();
    $registration = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretariat)
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

it('menampilkan antrean verifikasi kepada sekretariat pertandingan', function () {
    pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi")
        ->assertOk()
        ->assertSee($this->kontingen->name)
        // Sebab belum bisa disahkan ditulis di barisnya. Tombol mati tanpa
        // keterangan membuat panitia menebak, lalu menelepon official yang
        // juga tidak tahu.
        //
        // Kalimatnya menyebut KONTINGEN MANA yang tagihannya tertahan:
        // satu layar memuat banyak kontingen sekaligus, dan "tagihan belum
        // terbit" tanpa nama tidak memberi tahu ke mana panitia harus pergi.
        ->assertSee('Belum bisa disahkan')
        ->assertSee("Tagihan {$this->kontingen->name} belum terbit.");
});

/*
 * --------------------------------------------------------------------
 * Antrean verifikasi: pencarian, hitungan, dan panel berkas
 * --------------------------------------------------------------------
 */

it('mencari pendaftaran menurut nama atlet maupun kontingen', function () {
    /*
     * Layar ini sebelumnya tidak punya satu pun kotak cari, padahal daftarnya
     * ratusan baris dan panitia mencari orang yang namanya baru saja disebut
     * lewat pengeras suara.
     */
    $dicari = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    $dicari->athletes->first()->update(['name' => 'Zulkifli Akbar']);

    pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $hasil = $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi?status=semua&q=Zulkifli")
        ->assertOk()
        ->viewData('registrations');

    expect($hasil->total())->toBe(1)
        ->and($hasil->first()->id)->toBe($dicari->id);
});

it('membuat hitungan chip ikut menyusut oleh pencarian, tapi tidak oleh penyaring status', function () {
    /*
     * Chip menyaring DI DALAM hasil pencarian. Kalau hitungannya tetap
     * menyebut seluruh kejuaraan saat yang dicari cuma satu nama, panitia
     * menekan chip itu, mendapat nol hasil, lalu menyimpulkan pencariannya
     * rusak.
     *
     * Sebaliknya, penyaring status TIDAK boleh menyusutkan hitungan: panitia
     * yang sedang melihat "Ditolak" tetap perlu tahu masih ada berapa yang
     * menunggu.
     */
    $dicari = pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    $dicari->athletes->first()->update(['name' => 'Zulkifli Akbar']);

    pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);
    pendaftaranSiap($this->kontingen, $this->kelasC, $this->tournament);

    $tanpaCari = $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi?status=ditolak")
        ->assertOk()
        ->viewData('hitungan');

    // Penyaring status tidak menyusutkan hitungan.
    expect($tanpaCari['semua'])->toBe(3);

    $denganCari = $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi?status=semua&q=Zulkifli")
        ->assertOk()
        ->viewData('hitungan');

    // Pencarian menyusutkannya.
    expect($denganCari['semua'])->toBe(1);
});

it('membawa berkas peserta terpilih beserta yang kurang, disebut satu per satu', function () {
    /*
     * Yang KURANG disebut namanya, bukan cuma dihitung. Panitia yang membaca
     * "2 berkas kurang" tetap harus menelepon official untuk tahu berkas apa.
     */
    $athlete = Athlete::factory()->for($this->kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(['weight_claim' => 58.0]);

    // Sengaja hanya satu berkas yang diunggah.
    $wajib = $athlete->berkasWajib($this->tournament);
    RegistrationDocument::factory()->for($athlete)->create(['jenis' => $wajib[0]]);

    $registration = Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $registration->athletes()->attach($athlete);

    $berkas = $this->actingAs($this->sekretariat)
        ->get("/admin/turnamen/{$this->tournament->id}/verifikasi?status=semua&peserta={$registration->id}")
        ->assertOk()
        ->viewData('berkas');

    expect($berkas)->toHaveCount(1)
        ->and($berkas[0]['atlet'])->toBe($athlete->name)
        ->and(collect($berkas[0]['berkas'])->where('ada', true))->toHaveCount(1)
        ->and(collect($berkas[0]['berkas'])->where('ada', false)->count())->toBe(count($wajib) - 1);
});
