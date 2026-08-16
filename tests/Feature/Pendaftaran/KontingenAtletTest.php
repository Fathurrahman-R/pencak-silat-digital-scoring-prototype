<?php

use App\Enums\JenisBerkas;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\RegistrationDocument;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
});

it('mendaftarkan kontingen dan menolak nama kembar di kejuaraan yang sama', function () {
    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen", ['name' => 'Kontingen Semarang'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen", ['name' => 'Kontingen Semarang'])
        ->assertSessionHasErrors('name');

    expect($this->tournament->contingents()->count())->toBe(1);
});

it('mengizinkan nama kontingen sama di kejuaraan berbeda', function () {
    $lain = Tournament::factory()->create();

    foreach ([$this->tournament, $lain] as $tournament) {
        $this->actingAs($this->admin)
            ->post("/admin/turnamen/{$tournament->id}/kontingen", ['name' => 'Kontingen Semarang'])
            ->assertSessionHasNoErrors();
    }

    expect(Contingent::where('name', 'Kontingen Semarang')->count())->toBe(2);
});

it('menambahkan atlet ke kontingen', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet", [
            'name' => 'Bagas Pratama',
            'jenis_kelamin' => 'putra',
            'birth_date' => '2004-05-10',
            'weight_claim' => 58.4,
        ])
        ->assertSessionHasNoErrors();

    expect($kontingen->athletes()->count())->toBe(1);
});

it('menolak tanggal lahir di masa depan', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet", [
            'name' => 'Atlet Uji',
            'jenis_kelamin' => 'putra',
            'birth_date' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('birth_date');
});

/*
 * Berkas peserta adalah dokumen pribadi anak di bawah umur. Disimpan di disk
 * privat, bukan di storage publik — kejuaraan ini sengaja mengekspos sebagian
 * dirinya ke internet lewat tunnel halaman siaran langsung.
 */
it('menyimpan berkas peserta di disk privat', function () {
    Storage::fake('local');

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $atlet = Athlete::factory()->for($kontingen)->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet/{$atlet->id}/berkas", [
            'jenis' => JenisBerkas::BuktiUmur->value,
            'berkas' => UploadedFile::fake()->create('akta.pdf', 200, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $berkas = $atlet->documents()->firstOrFail();

    expect($berkas->jenis)->toBe(JenisBerkas::BuktiUmur)
        ->and($berkas->original_name)->toBe('akta.pdf');

    Storage::disk('local')->assertExists($berkas->path);
    Storage::disk('public')->assertMissing($berkas->path);
});

/*
 * Satu jenis berkas satu yang berlaku. Kalau unggahan lama dibiarkan menumpuk,
 * panitia harus menebak mana surat sehat yang masih berlaku.
 */
it('mengganti berkas lama saat jenis yang sama diunggah ulang', function () {
    Storage::fake('local');

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $atlet = Athlete::factory()->for($kontingen)->create();
    $url = "/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet/{$atlet->id}/berkas";

    $this->actingAs($this->admin)->post($url, [
        'jenis' => JenisBerkas::SuratSehat->value,
        'berkas' => UploadedFile::fake()->create('sehat-lama.pdf', 100, 'application/pdf'),
    ]);

    $lama = $atlet->documents()->firstOrFail();

    $this->actingAs($this->admin)->post($url, [
        'jenis' => JenisBerkas::SuratSehat->value,
        'berkas' => UploadedFile::fake()->create('sehat-baru.pdf', 100, 'application/pdf'),
    ]);

    expect($atlet->documents()->count())->toBe(1)
        ->and($atlet->documents()->first()->original_name)->toBe('sehat-baru.pdf');

    Storage::disk('local')->assertMissing($lama->path);
});

it('menolak berkas yang tipenya tidak diterima', function () {
    Storage::fake('local');

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $atlet = Athlete::factory()->for($kontingen)->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet/{$atlet->id}/berkas", [
            'jenis' => JenisBerkas::BuktiUmur->value,
            'berkas' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('berkas');
});

it('menolak berkas milik atlet kontingen lain', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $lain = Contingent::factory()->for($this->tournament)->create();

    $atlet = Athlete::factory()->for($lain)->create();
    $berkas = RegistrationDocument::factory()->for($atlet)->create();

    $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$kontingen->id}/atlet/{$atlet->id}/berkas/{$berkas->id}")
        ->assertNotFound();
});

/*
 * Official kontingen hanya melihat kontingennya sendiri. Dibalas 404, bukan
 * 403: keberadaan kontingen sebelah beserta jumlah atletnya bukan hal yang
 * perlu diketahui pesaingnya.
 */
it('membatasi official pada kontingennya sendiri', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    $milikSendiri = Contingent::factory()->for($this->tournament)->create(['user_id' => $official->id]);
    $milikOrangLain = Contingent::factory()->for($this->tournament)->create();

    $this->actingAs($official)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$milikSendiri->id}/atlet")
        ->assertOk();

    $this->actingAs($official)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen/{$milikOrangLain->id}/atlet")
        ->assertNotFound();
});

it('menampilkan hanya kontingen sendiri di daftar official', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    Contingent::factory()->for($this->tournament)->create([
        'user_id' => $official->id, 'name' => 'Kontingen Sendiri',
    ]);
    Contingent::factory()->for($this->tournament)->create(['name' => 'Kontingen Tetangga']);

    $this->actingAs($official)
        ->get("/admin/turnamen/{$this->tournament->id}/kontingen")
        ->assertOk()
        ->assertSee('Kontingen Sendiri')
        ->assertDontSee('Kontingen Tetangga');
});

it('menghitung berkas wajib berbeda untuk pesilat putri remaja', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $putri = Athlete::factory()->for($kontingen)->putri()
        ->golongan(App\Enums\GolonganUsia::Remaja, new DateTime('2026-09-01'))->create();
    $putra = Athlete::factory()->for($kontingen)->putra()
        ->golongan(App\Enums\GolonganUsia::Remaja, new DateTime('2026-09-01'))->create();

    expect($putri->berkasWajib($this->tournament))->toContain(JenisBerkas::SuratTidakHamil)
        ->and($putra->berkasWajib($this->tournament))->not->toContain(JenisBerkas::SuratTidakHamil);
});
