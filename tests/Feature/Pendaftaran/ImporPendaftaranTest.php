<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Http\UploadedFile;

/*
 * Impor daftar peserta dari CSV.
 *
 * Yang dijaga di sini bukan sekadar "berkas terbaca", melainkan tiga janji
 * yang membuat fitur ini aman dipakai sehari sebelum kejuaraan:
 *
 *   - pratinjau TIDAK menulis apa pun, walau ia menjalankan impor sungguhan;
 *   - baris ganda tidak menggandakan atlet, karena berkas yang sama akan
 *     diunggah ulang berkali-kali sambil diperbaiki satu-dua barisnya;
 *   - impor tunduk pada pembekuan tagihan, sama seperti tombol Daftarkan --
 *     tanpa itu ia jadi pintu belakang yang menambah nomor tanpa menagihnya.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-10-10']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->contingent = Contingent::factory()->for($this->tournament)->create();

    $this->berkas = function (string $isi) {
        return UploadedFile::fake()->createWithContent('peserta.csv', $isi);
    };

    $this->alamat = fn (string $aksi) => "/admin/turnamen/{$this->tournament->id}"
        ."/kontingen/{$this->contingent->id}/impor/{$aksi}";
});

it('menyediakan berkas contoh berisi seluruh kolom yang dikenali', function () {
    $balasan = $this->actingAs($this->admin)->get(($this->alamat)('contoh'));

    $balasan->assertOk();

    $isi = $balasan->streamedContent();

    expect($isi)->toContain('nama,jenis_kelamin,tanggal_lahir,berat,tanding,nomor_jurus,regu');
});

/**
 * Pratinjau menjalankan impor sungguhan lalu memutarnya balik. Kalau
 * transaksinya bocor, seluruh berkas masuk tanpa satu pun orang menekan
 * Terapkan -- dan itu terjadi diam-diam.
 */
it('tidak menulis apa pun saat pratinjau', function () {
    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat,tanding
    Peserta Satu,putra,2000-03-03,58,ya
    CSV;

    $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)])
        ->assertOk()
        ->assertSee('Peserta Satu');

    expect(Athlete::where('contingent_id', $this->contingent->id)->count())->toBe(0)
        ->and(Registration::where('contingent_id', $this->contingent->id)->count())->toBe(0);
});

it('membuat atlet dan pendaftaran tandingnya saat diterapkan', function () {
    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat,tanding
    Peserta Satu,putra,2000-03-03,58,ya
    Peserta Dua,L,15/06/2001,52,ya
    CSV;

    $pratinjau = $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)]);

    $token = $pratinjau->viewData('token');

    $this->actingAs($this->admin)
        ->post(($this->alamat)('terapkan'), ['token' => $token])
        ->assertSessionHasNoErrors();

    $atlet = Athlete::where('contingent_id', $this->contingent->id)->orderBy('name')->get();

    // Diurutkan menurut nama: "Peserta Dua" lebih dulu daripada "Peserta Satu".
    expect($atlet)->toHaveCount(2)
        // Kedua bentuk tanggal terbaca, dan keduanya jadi tanggal yang sama benar.
        ->and($atlet->firstWhere('name', 'Peserta Satu')->birth_date->toDateString())->toBe('2000-03-03')
        ->and($atlet->firstWhere('name', 'Peserta Dua')->birth_date->toDateString())->toBe('2001-06-15')
        // "L" terbaca sebagai putra.
        ->and($atlet->firstWhere('name', 'Peserta Dua')->jenis_kelamin->value)->toBe('putra');

    expect(Registration::where('contingent_id', $this->contingent->id)->count())->toBe(2);
});

/**
 * Berkas yang sama diunggah dua kali adalah bentuk pemakaian yang paling
 * sering terjadi: panitia memperbaiki satu baris lalu mengunggah ulang
 * seluruhnya.
 */
it('memakai ulang atlet yang sudah ada alih-alih menggandakannya', function () {
    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat
    Peserta Satu,putra,2000-03-03,58
    CSV;

    foreach ([58, 59] as $berat) {
        $isi = str_replace(',58', ",{$berat}", $csv);

        $pratinjau = $this->actingAs($this->admin)
            ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($isi)]);

        $this->actingAs($this->admin)
            ->post(($this->alamat)('terapkan'), ['token' => $pratinjau->viewData('token')]);
    }

    $atlet = Athlete::where('contingent_id', $this->contingent->id)->get();

    expect($atlet)->toHaveCount(1)
        // Berat yang berubah antar unggahan memang wajar -- panitia
        // memperbaikinya setelah timbang percobaan.
        ->and((float) $atlet[0]->weight_claim)->toBe(59.0);
});

/**
 * Satu nomor Ganda lahir dari DUA baris CSV yang disatukan kolom `regu`.
 * Tanpa penggabungan itu, keduanya jadi dua pendaftaran tunggal yang
 * masing-masing ditolak karena kurang pesilat.
 */
it('menyatukan baris ber-regu sama jadi satu pendaftaran jurus', function () {
    $nomor = $this->tournament->jurusEvents
        ->first(fn ($n) => $n->nama() === 'Jurus Ganda Putra Dewasa');

    $csv = <<<CSV
    nama,jenis_kelamin,tanggal_lahir,berat,nomor_jurus,regu
    Ganda Satu,putra,2002-02-02,60,{$nomor->nama()},GA
    Ganda Dua,putra,2002-04-04,61,{$nomor->nama()},GA
    CSV;

    $pratinjau = $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)]);

    $this->actingAs($this->admin)
        ->post(($this->alamat)('terapkan'), ['token' => $pratinjau->viewData('token')]);

    $pendaftaran = Registration::where('contingent_id', $this->contingent->id)->with('athletes')->get();

    expect($pendaftaran)->toHaveCount(1)
        ->and($pendaftaran[0]->jurus_event_id)->toBe($nomor->id)
        ->and($pendaftaran[0]->athletes)->toHaveCount(2);
});

it('menolak baris yang datanya tidak terbaca tanpa menjatuhkan baris lain', function () {
    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat,tanding
    Peserta Baik,putra,2000-03-03,58,ya
    Peserta Gender,robot,2000-01-01,55,ya
    Peserta Tanggal,putra,32/13/2000,55,ya
    ,putra,2000-01-01,55,ya
    CSV;

    $pratinjau = $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)]);

    $pratinjau->assertSee('Jenis kelamin tidak terbaca', false)
        ->assertSee('Tanggal lahir tidak terbaca', false)
        ->assertSee('Kolom nama kosong.', false);

    $this->actingAs($this->admin)
        ->post(($this->alamat)('terapkan'), ['token' => $pratinjau->viewData('token')]);

    expect(Athlete::where('contingent_id', $this->contingent->id)->pluck('name')->all())
        ->toBe(['Peserta Baik']);
});

it('menolak berkas yang baris pertamanya bukan nama kolom', function () {
    $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)("a,b,c\n1,2,3")])
        ->assertSessionHasErrors('berkas');
});

it('menolak alamat yang bukan Google Spreadsheet', function () {
    $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['url' => 'https://contoh.test/berkas.csv'])
        ->assertSessionHasErrors('berkas');
});

/**
 * Tanpa penjagaan ini impor jadi pintu belakang: tagihan sudah dikunci pada
 * nominal delapan nomor, lalu satu berkas CSV menambah tiga puluh nomor lagi
 * yang tidak pernah ditagih.
 */
it('menolak impor saat pendaftaran dibekukan tagihan', function () {
    Invoice::create([
        'contingent_id' => $this->contingent->id,
        'number' => 'INV-IMPOR-001',
        'status' => 'lunas',
        'total_amount' => 150000,
        'locked_at' => now(),
        'paid_at' => now(),
    ]);

    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat,tanding
    Peserta Satu,putra,2000-03-03,58,ya
    CSV;

    $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)])
        ->assertSessionHasErrors('berkas');

    expect(Athlete::where('contingent_id', $this->contingent->id)->count())->toBe(0);
});

it('menolak token pratinjau milik kontingen lain', function () {
    $csv = <<<'CSV'
    nama,jenis_kelamin,tanggal_lahir,berat,tanding
    Peserta Satu,putra,2000-03-03,58,ya
    CSV;

    $pratinjau = $this->actingAs($this->admin)
        ->post(($this->alamat)('pratinjau'), ['berkas' => ($this->berkas)($csv)]);

    $lain = Contingent::factory()->for($this->tournament)->create();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/kontingen/{$lain->id}/impor/terapkan", [
            'token' => $pratinjau->viewData('token'),
        ])
        ->assertSessionHasErrors('berkas');

    expect(Athlete::where('contingent_id', $lain->id)->count())->toBe(0);
});
