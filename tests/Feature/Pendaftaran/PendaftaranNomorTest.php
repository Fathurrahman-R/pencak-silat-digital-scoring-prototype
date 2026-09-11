<?php

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Registration;
use App\Models\RegistrationDocument;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Keuangan\InvoiceBuilder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
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

/*
 * Begitu sesi pembayaran dibuat, pendaftaran dibekukan. Tanpa ini, yang dibayar
 * bisa berbeda dari yang ditagih: official menekan bayar untuk satu nominal,
 * menambah atlet, lalu uang nominal lama yang masuk.
 */
it('menolak menambah pendaftaran saat tagihan sedang menunggu pembayaran', function () {
    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);

    $pertama = pesilatDewasa($this->kontingen);
    $pendaftaran = Registration::factory()->for($this->kontingen)->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach($pertama);

    $builder = new InvoiceBuilder;
    (new KelolaInvoice($builder))->kunci($builder->untuk($this->kontingen));

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$this->kontingen->id}/pendaftaran/tanding", [
            'athlete_id' => pesilatDewasa($this->kontingen, ['weight_claim' => 57.0])->id,
            'weight_class_id' => $this->kelasC->id,
        ])
        ->assertSessionHasErrors('pendaftaran');

    expect($this->kontingen->registrations()->count())->toBe(1);
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

/*
 * --------------------------------------------------------------------
 * Panel timbang badan: antrean, penyaring, dan batas kelas
 * --------------------------------------------------------------------
 */

it('mengurutkan antrean timbang menurut urutan tayang partai, bukan abjad nama', function () {
    /*
     * Petugas timbang bekerja mengikuti antrean gelanggang: yang tayang
     * pertama harus ditimbang sebelum yang tayang belakangan. Daftar abjad
     * tidak membawa satu pun petunjuk itu, dan petugas yang mengikutinya akan
     * menahan partai pertama karena pesilatnya berhuruf Z.
     */
    $bracket = Bracket::create(['weight_class_id' => $this->kelasC->id, 'size' => 2]);
    $gelanggang = Arena::factory()->for($this->tournament)->create();

    $awal = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $awal->athletes()->attach(pesilatDewasa($this->kontingen, ['name' => 'Zulkifli Akbar']));

    $akhir = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $akhir->athletes()->attach(pesilatDewasa($this->kontingen, ['name' => 'Andi Pratama']));

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $awal->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $gelanggang->id, 'order_in_arena' => 1,
    ]);

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 2,
        'red_registration_id' => $akhir->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $gelanggang->id, 'order_in_arena' => 2,
    ]);

    $urut = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/timbang?saringan=semua")
        ->assertOk()
        ->viewData('registrations')
        ->pluck('id');

    expect($urut->first())->toBe($awal->id)
        ->and($urut[1])->toBe($akhir->id);
});

it('membawa hitungan tiap penyaring, dihitung sebelum penyaringan', function () {
    /*
     * Chip penyaring wajib membawa angkanya sendiri. Petugas perlu tahu masih
     * ada berapa yang belum ditimbang SEBELUM menekan chipnya -- itulah
     * satu-satunya angka yang menentukan ia boleh pulang atau tidak.
     */
    $lolos = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $lolos->athletes()->attach(pesilatDewasa($this->kontingen));

    $belum = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $belum->athletes()->attach(pesilatDewasa($this->kontingen));

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/timbang/{$lolos->id}", ['weight' => 58]);

    $hitungan = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/timbang?saringan=belum")
        ->assertOk()
        ->viewData('hitungan');

    // Hitungan tidak ikut menyusut walau daftarnya sedang tersaring.
    expect($hitungan['lolos'])->toBe(1)
        ->and($hitungan['belum'])->toBe(1)
        ->and($hitungan['semua'])->toBe(2);
});

it('membawa batas kelas peserta terpilih sebagai angka, bukan cuma kalimat', function () {
    /*
     * Panel menyatakan lolos atau tidak SAAT angkanya diketik, sebelum tangan
     * petugas berpindah ke tombol. Itu hanya mungkin kalau batasnya sampai ke
     * peramban sebagai angka, lengkap dengan aturan batas terbuka: kelas C
     * dewasa putra berbunyi "di atas 55 sampai 60", jadi 55,0 kg tidak lolos
     * sedangkan 60,0 kg lolos.
     */
    $pendaftaran = Registration::factory()->for($this->kontingen)->terverifikasi()
        ->create(['weight_class_id' => $this->kelasC->id]);
    $pendaftaran->athletes()->attach(pesilatDewasa($this->kontingen));

    $batas = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/timbang?saringan=semua&peserta={$pendaftaran->id}")
        ->assertOk()
        ->viewData('batas');

    expect($batas['min'])->toBe(55.0)
        ->and($batas['max'])->toBe(60.0)
        ->and($batas['min_eksklusif'])->toBeTrue()
        ->and($batas['nama'])->toBe($this->kelasC->name);
});
