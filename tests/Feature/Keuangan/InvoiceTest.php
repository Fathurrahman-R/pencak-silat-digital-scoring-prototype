<?php

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Enums\KategoriPertandingan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Registration;
use App\Models\Tournament;
use App\Support\Keuangan\InvoiceBuilder;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->builder = new InvoiceBuilder;

    $this->kelasC = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
});

function tarifUmum(Tournament $tournament, int $amount = 150_000): FeeSchedule
{
    return FeeSchedule::factory()->for($tournament)->create(['amount' => $amount]);
}

function daftarTanding(Contingent $kontingen, $kelas, int $jumlahAtlet = 1): Registration
{
    $registration = Registration::factory()->for($kontingen)->create(['weight_class_id' => $kelas->id]);

    foreach (range(1, $jumlahAtlet) as $urutan) {
        $registration->athletes()->attach(
            Athlete::factory()->for($kontingen)->create(),
            ['position' => $urutan],
        );
    }

    return $registration;
}

function daftarJurus(Contingent $kontingen, Tournament $tournament, JenisJurus $jenis): Registration
{
    $nomor = $tournament->jurusEvents()
        ->where('jenis', $jenis)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $registration = Registration::factory()->for($kontingen)->create(['jurus_event_id' => $nomor->id]);

    foreach (range(1, $jenis->jumlahPesilat()) as $urutan) {
        $registration->athletes()->attach(
            Athlete::factory()->for($kontingen)->create(),
            ['position' => $urutan],
        );
    }

    return $registration;
}

it('menagih satu baris per pendaftaran beserta biaya tetap kontingen', function () {
    tarifUmum($this->tournament, 150_000);
    FeeSchedule::factory()->for($this->tournament)->kontingen(250_000)->create();

    daftarTanding($this->kontingen, $this->kelasC);
    daftarTanding($this->kontingen, $this->kelasC);

    $invoice = $this->builder->untuk($this->kontingen);

    expect($invoice->items)->toHaveCount(3)
        ->and($invoice->total_amount)->toBe(550_000);
});

/*
 * Nomor beregu ditagih per tim, bukan per orang. Ini jatuh sendiri dari bentuk
 * datanya — satu pendaftaran berisi tiga atlet tetap satu baris tagihan — jadi
 * tidak ada aturan khusus yang bisa lupa diterapkan.
 */
it('menagih nomor regu sekali untuk satu tim bertiga', function () {
    tarifUmum($this->tournament, 200_000);

    $regu = daftarJurus($this->kontingen, $this->tournament, JenisJurus::Regu);

    $invoice = $this->builder->untuk($this->kontingen);

    expect($regu->athletes)->toHaveCount(3)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->total_amount)->toBe(200_000);
});

it('memakai tarif yang paling khusus', function () {
    // Tarif umum untuk semua, lalu dua pengecualian yang makin khusus.
    tarifUmum($this->tournament, 100_000);

    FeeSchedule::factory()->for($this->tournament)->create([
        'kategori' => KategoriPertandingan::Tanding,
        'amount' => 175_000,
    ]);

    FeeSchedule::factory()->for($this->tournament)->create([
        'kategori' => KategoriPertandingan::Tanding,
        'golongan_usia' => GolonganUsia::Dewasa,
        'amount' => 225_000,
    ]);

    daftarTanding($this->kontingen, $this->kelasC);

    expect($this->builder->untuk($this->kontingen)->total_amount)->toBe(225_000);
});

it('memakai tarif umum saat tidak ada pengecualian yang cocok', function () {
    tarifUmum($this->tournament, 120_000);

    FeeSchedule::factory()->for($this->tournament)->create([
        'kategori' => KategoriPertandingan::Tanding,
        'golongan_usia' => GolonganUsia::Remaja,
        'amount' => 999_000,
    ]);

    daftarTanding($this->kontingen, $this->kelasC);

    expect($this->builder->untuk($this->kontingen)->total_amount)->toBe(120_000);
});

it('tidak menagih pendaftaran yang ditolak panitia', function () {
    tarifUmum($this->tournament, 150_000);

    daftarTanding($this->kontingen, $this->kelasC);
    daftarTanding($this->kontingen, $this->kelasC)->update(['status' => StatusPendaftaran::Ditolak]);

    expect($this->builder->untuk($this->kontingen)->total_amount)->toBe(150_000);
});

/*
 * Yang gugur di timbang badan tetap ditagih. Pesertanya sudah didaftarkan dan
 * tempatnya sudah disiapkan; pembatalan tidak mengembalikan dana.
 */
it('tetap menagih pendaftaran yang gugur di timbang badan', function () {
    tarifUmum($this->tournament, 150_000);

    daftarTanding($this->kontingen, $this->kelasC)->update(['status' => StatusPendaftaran::Gugur]);

    expect($this->builder->untuk($this->kontingen)->total_amount)->toBe(150_000);
});

it('menyusun ulang tagihan draf saat pendaftaran bertambah', function () {
    tarifUmum($this->tournament, 150_000);

    daftarTanding($this->kontingen, $this->kelasC);
    $invoice = $this->builder->untuk($this->kontingen);
    expect($invoice->total_amount)->toBe(150_000);

    daftarTanding($this->kontingen, $this->kelasC);

    expect($this->builder->untuk($this->kontingen)->total_amount)->toBe(300_000);
});

/*
 * Celah yang ditutup penguncian: official menekan bayar untuk satu nominal,
 * menambah atlet, lalu uang nominal lama yang masuk — sementara tagihannya
 * sudah berubah.
 */
it('membekukan nominal setelah tagihan dikunci', function () {
    tarifUmum($this->tournament, 150_000);
    daftarTanding($this->kontingen, $this->kelasC);

    $kelola = new KelolaInvoice($this->builder);
    $invoice = $kelola->kunci($this->builder->untuk($this->kontingen));

    expect($invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($invoice->locked_at)->not->toBeNull()
        ->and($invoice->total_amount)->toBe(150_000);

    daftarTanding($this->kontingen, $this->kelasC);

    expect($this->builder->untuk($this->kontingen->fresh())->total_amount)->toBe(150_000);
});

it('mencairkan kembali tagihan yang kedaluwarsa lalu menghitung ulang', function () {
    tarifUmum($this->tournament, 150_000);
    daftarTanding($this->kontingen, $this->kelasC);

    $kelola = new KelolaInvoice($this->builder);
    $invoice = $kelola->kunci($this->builder->untuk($this->kontingen));

    daftarTanding($this->kontingen, $this->kelasC);
    $invoice = $kelola->cairkan($invoice);

    expect($invoice->status)->toBe(StatusInvoice::Draf)
        ->and($invoice->locked_at)->toBeNull()
        ->and($invoice->total_amount)->toBe(300_000);
});

it('menolak mengunci tagihan bernilai nol', function () {
    $kelola = new KelolaInvoice($this->builder);

    $kelola->kunci($this->builder->untuk($this->kontingen));
})->throws(RuntimeException::class, 'bernilai nol');

/*
 * Tagihan yang belum dikunci belum tentu bernominal sama dengan yang dibayar,
 * jadi tidak boleh langsung ditandai lunas.
 */
it('menolak menandai lunas tagihan yang belum dikunci', function () {
    tarifUmum($this->tournament, 150_000);
    daftarTanding($this->kontingen, $this->kelasC);

    (new KelolaInvoice($this->builder))
        ->tandaiLunas($this->builder->untuk($this->kontingen), 'manual');
})->throws(RuntimeException::class, 'belum dikunci');

/*
 * Gerbang pembayaran mengirim notifikasi yang sama berkali-kali sampai
 * menerima balasan sukses.
 */
it('aman dipanggil berkali-kali saat menandai lunas', function () {
    tarifUmum($this->tournament, 150_000);
    daftarTanding($this->kontingen, $this->kelasC);

    $kelola = new KelolaInvoice($this->builder);
    $invoice = $kelola->kunci($this->builder->untuk($this->kontingen));

    $pertama = $kelola->tandaiLunas($invoice, 'midtrans');
    $waktuBayar = $pertama->paid_at;

    $kedua = $kelola->tandaiLunas($pertama->fresh(), 'manual');

    expect($kedua->status)->toBe(StatusInvoice::Lunas)
        ->and($kedua->paid_via)->toBe('midtrans')
        ->and($kedua->paid_at->timestamp)->toBe($waktuBayar->timestamp);
});

it('menolak mencairkan tagihan yang sudah lunas', function () {
    tarifUmum($this->tournament, 150_000);
    daftarTanding($this->kontingen, $this->kelasC);

    $kelola = new KelolaInvoice($this->builder);
    $invoice = $kelola->tandaiLunas($kelola->kunci($this->builder->untuk($this->kontingen)), 'manual');

    $kelola->cairkan($invoice);
})->throws(RuntimeException::class, 'tidak sedang menunggu pembayaran');

it('memberi nomor tagihan yang unik dan berurutan', function () {
    tarifUmum($this->tournament, 150_000);

    $kedua = Contingent::factory()->for($this->tournament)->create();

    $satu = $this->builder->untuk($this->kontingen);
    $dua = $this->builder->untuk($kedua);

    expect($satu->number)->not->toBe($dua->number)
        ->and((int) substr($dua->number, -5))->toBe((int) substr($satu->number, -5) + 1);
});

it('menyimpan keterangan baris sebagai teks jadi', function () {
    tarifUmum($this->tournament, 150_000);

    $registration = daftarTanding($this->kontingen, $this->kelasC);
    $nama = $registration->athletes->first()->name;

    $baris = $this->builder->untuk($this->kontingen)->items()->firstOrFail();

    expect($baris->description)->toContain('Kelas C')->toContain($nama);
});
