<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisBerkas;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\RegistrationDocument;
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

function pesilatDewasa(Contingent $kontingen, array $ganti = []): Athlete
{
    return Athlete::factory()->for($kontingen)->putra()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))
        ->create(array_merge(['weight_claim' => 58.0], $ganti));
}

/** Melengkapi berkas wajib supaya pendaftaran bisa diajukan. */
function lengkapiBerkas(Athlete $athlete, Tournament $tournament): void
{
    foreach ($athlete->berkasWajib($tournament) as $jenis) {
        RegistrationDocument::factory()->for($athlete)->create(['jenis' => $jenis]);
    }
}

it('mendaftarkan atlet ke kelas tanding', function () {
    $pesilat = pesilatDewasa($this->kontingen);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/tanding", [
            'athlete_id' => $pesilat->id,
            'weight_class_id' => $this->kelasC->id,
        ])
        ->assertSessionHasNoErrors();

    $pendaftaran = $this->kontingen->registrations()->firstOrFail();

    expect($pendaftaran->weight_class_id)->toBe($this->kelasC->id)
        ->and($pendaftaran->status)->toBe(StatusPendaftaran::Draf)
        ->and($pendaftaran->athletes)->toHaveCount(1);
});

it('menolak pendaftaran yang tidak memenuhi kelayakan beserta alasannya', function () {
    $putri = Athlete::factory()->for($this->kontingen)->putri()
        ->golongan(GolonganUsia::Dewasa, new DateTime('2026-09-01'))->create(['weight_claim' => 58.0]);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/tanding", [
            'athlete_id' => $putri->id,
            'weight_class_id' => $this->kelasC->id,
        ])
        ->assertSessionHasErrors('weight_class_id');

    expect($this->kontingen->registrations()->count())->toBe(0);
});

it('mendaftarkan nomor jurus beregu dengan urutan pesilat tersimpan', function () {
    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Regu)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $tim = collect(range(1, 3))->map(fn () => pesilatDewasa($this->kontingen));

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/jurus", [
            'jurus_event_id' => $nomor->id,
            'athlete_ids' => $tim->pluck('id')->all(),
        ])
        ->assertSessionHasNoErrors();

    $pendaftaran = $this->kontingen->registrations()->firstOrFail();

    expect($pendaftaran->athletes)->toHaveCount(3)
        ->and($pendaftaran->athletes->pluck('pivot.position')->all())->toBe([1, 2, 3]);
});

/*
 * Id atlet kontingen lain yang disisipkan ke formulir tidak boleh pernah
 * sampai ke pemeriksaan kelayakan — atletnya diambil lewat relasi kontingen,
 * bukan lewat pencarian global.
 */
it('mengabaikan atlet kontingen lain yang disisipkan ke formulir', function () {
    $lain = Contingent::factory()->for($this->tournament)->create();

    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Ganda)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/jurus", [
            'jurus_event_id' => $nomor->id,
            'athlete_ids' => [pesilatDewasa($this->kontingen)->id, pesilatDewasa($lain)->id],
        ])
        ->assertSessionHasErrors('jurus_event_id');

    expect($this->kontingen->registrations()->count())->toBe(0);
});

/*
 * Official lazim mendaftarkan atlet lebih dulu lalu menyusulkan surat sehatnya.
 * Kelengkapan berkas karena itu diperiksa saat mengajukan, bukan saat mendaftar.
 */
it('menolak pengajuan saat berkas wajib belum lengkap', function () {
    $pesilat = pesilatDewasa($this->kontingen);
    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/{$pendaftaran->id}/ajukan")
        ->assertSessionHasErrors('berkas');

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Draf);
});

it('mengajukan pendaftaran saat berkas sudah lengkap', function () {
    $pesilat = pesilatDewasa($this->kontingen);
    lengkapiBerkas($pesilat, $this->tournament);

    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/{$pendaftaran->id}/ajukan")
        ->assertSessionHasNoErrors();

    expect($pendaftaran->fresh())
        ->status->toBe(StatusPendaftaran::Diajukan)
        ->submitted_at->not->toBeNull();
});

it('menutup pendaftaran kontingen lain dari official', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    $this->actingAs($official)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran")
        ->assertNotFound();
});

it('mencatat timbang badan dan menggugurkan yang di luar kelas', function () {
    $pesilat = pesilatDewasa($this->kontingen);
    $pendaftaran = Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pesilat);

    // Kelas C dewasa putra: di atas 55 kg sampai 60 kg.
    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/timbang/{$pendaftaran->id}", ['weight' => 61.2])
        ->assertSessionHasNoErrors();

    $timbangan = $pendaftaran->weightIns()->firstOrFail();

    expect($timbangan->passed)->toBeFalse()
        ->and($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Gugur);
});

it('meloloskan berat yang masuk rentang kelas', function () {
    $pesilat = pesilatDewasa($this->kontingen);
    $pendaftaran = Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/timbang/{$pendaftaran->id}", ['weight' => 59.5]);

    expect($pendaftaran->weightIns()->first()->passed)->toBeTrue()
        ->and($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Diajukan);
});

/*
 * Penimbangan ulang memang dimaksudkan memberi kesempatan kedua. Status yang
 * tidak ikut pulih membuat kesempatan itu tidak berarti apa-apa.
 */
it('memulihkan status setelah penimbangan ulang yang lolos', function () {
    $pesilat = pesilatDewasa($this->kontingen);
    $pendaftaran = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $url = "/admin/turnamen/{$this->tournament->id}/timbang/{$pendaftaran->id}";

    $this->actingAs($this->admin)->post($url, ['weight' => 61.2]);
    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Gugur);

    $this->actingAs($this->admin)->post($url, ['weight' => 59.0]);

    expect($pendaftaran->fresh()->status)->toBe(StatusPendaftaran::Terverifikasi)
        ->and($pendaftaran->weightIns()->count())->toBe(2);
});

/*
 * Pra Usia Dini dan Usia Dini 1 tidak menjalani timbang badan (Pasal 2 ayat 4),
 * dan kategori Jurus tidak mengenal kelas berat sama sekali.
 */
it('tidak menampilkan peserta jurus di panel timbang badan', function () {
    $nomor = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $pesilat = pesilatDewasa($this->kontingen, ['name' => 'Peserta Jurus Saja']);
    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['jurus_event_id' => $nomor->id]);
    $pendaftaran->athletes()->attach($pesilat);

    $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/timbang")
        ->assertOk()
        ->assertDontSee('Peserta Jurus Saja');
});
