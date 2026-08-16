<?php

use App\Actions\Keuangan\KelolaInvoice;
use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusInvoice;
use App\Models\Athlete;
use App\Models\AuditLog;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->bendahara = User::factory()->create();
    $this->bendahara->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    FeeSchedule::factory()->for($this->tournament)->create(['amount' => 150_000]);

    $this->kelasC = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
});

function kontingenBertagihan(Tournament $tournament, $kelas, string $nama = 'Kontingen Uji'): Contingent
{
    $kontingen = Contingent::factory()->for($tournament)->create(['name' => $nama]);

    $registration = Registration::factory()->for($kontingen)->create(['weight_class_id' => $kelas->id]);
    $registration->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    (new InvoiceBuilder)->untuk($kontingen);

    return $kontingen->refresh();
}

it('meringkas total masuk dan tunggakan seluruh kejuaraan', function () {
    $lunas = kontingenBertagihan($this->tournament, $this->kelasC, 'Kontingen Lunas');
    kontingenBertagihan($this->tournament, $this->kelasC, 'Kontingen Nunggak');

    $builder = new InvoiceBuilder;
    $kelola = new KelolaInvoice($builder);
    $kelola->tandaiLunas($kelola->kunci($builder->untuk($lunas)), 'manual');

    $this->actingAs($this->bendahara)
        ->get("/admin/turnamen/{$this->tournament->id}/bendahara")
        ->assertOk()
        ->assertSee('Kontingen Lunas')
        ->assertSee('Kontingen Nunggak');
});

/*
 * Ringkasan dihitung dari seluruh tagihan, bukan dari yang sedang tersaring.
 * Bendahara yang menyaring "menunggu pembayaran" tetap perlu melihat total
 * masuk yang sebenarnya, bukan nol.
 */
it('mempertahankan angka ringkasan saat daftar sedang disaring', function () {
    $lunas = kontingenBertagihan($this->tournament, $this->kelasC, 'Kontingen Lunas');

    $builder = new InvoiceBuilder;
    $kelola = new KelolaInvoice($builder);
    $kelola->tandaiLunas($kelola->kunci($builder->untuk($lunas)), 'manual');

    $this->actingAs($this->bendahara)
        ->get("/admin/turnamen/{$this->tournament->id}/bendahara?status=draf")
        ->assertOk()
        ->assertSee('Rp 150.000');
});

it('menandai tagihan lunas manual beserta bukti dan jejak audit', function () {
    Storage::fake('local');

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);
    $invoice = $kontingen->invoice;

    $this->actingAs($this->bendahara)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$invoice->id}/lunas", [
            'note' => 'Transfer BCA ref 998877',
            'paid_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $invoice->refresh();
    $manual = $invoice->manualPayments()->firstOrFail();

    expect($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($invoice->paid_via)->toBe('manual')
        ->and($manual->note)->toBe('Transfer BCA ref 998877')
        ->and($manual->amount)->toBe(150_000)
        ->and($manual->recorded_by)->toBe($this->bendahara->id);

    Storage::disk('local')->assertExists($manual->proof_path);

    $jejak = AuditLog::where('action', 'invoice.lunas_manual')->firstOrFail();

    expect($jejak->user_id)->toBe($this->bendahara->id)
        ->and($jejak->auditable_id)->toBe($invoice->id)
        ->and($jejak->properties['keterangan'])->toBe('Transfer BCA ref 998877');
});

/*
 * Pembayaran manual tidak punya pihak ketiga yang bisa dimintai konfirmasi.
 * Yang tersisa hanya bukti yang diunggah dan nama bendahara di jejak audit —
 * karena itu keduanya diwajibkan, bukan disarankan.
 */
it('menolak penandaan lunas tanpa bukti', function () {
    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    $this->actingAs($this->bendahara)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas", [
            'note' => 'Katanya sudah transfer',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('proof');

    expect($kontingen->invoice->fresh()->status)->toBe(StatusInvoice::Draf);
});

it('menolak penandaan lunas tanpa keterangan', function () {
    Storage::fake('local');

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    $this->actingAs($this->bendahara)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas", [
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('note');
});

it('menolak tanggal pembayaran di masa depan', function () {
    Storage::fake('local');

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    $this->actingAs($this->bendahara)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas", [
            'note' => 'Transfer',
            'paid_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('paid_at');
});

/*
 * Tagihan yang masih draf dikunci lebih dulu, supaya nominal yang dinyatakan
 * lunas benar-benar nominal yang berlaku saat itu — bukan angka yang masih
 * bisa berubah sesudahnya.
 */
it('mengunci tagihan draf sebelum menandainya lunas', function () {
    Storage::fake('local');

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    expect($kontingen->invoice->status)->toBe(StatusInvoice::Draf);

    $this->actingAs($this->bendahara)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas", [
            'note' => 'Setor tunai sekretariat',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'proof' => UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg'),
        ]);

    $invoice = $kontingen->invoice->fresh();

    expect($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($invoice->locked_at)->not->toBeNull();
});

it('menolak menandai lunas tagihan yang sudah lunas', function () {
    Storage::fake('local');

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);
    $url = "/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas";

    $isi = fn () => [
        'note' => 'Transfer',
        'paid_at' => now()->format('Y-m-d\TH:i'),
        'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
    ];

    $this->actingAs($this->bendahara)->post($url, $isi())->assertSessionHasNoErrors();
    $this->actingAs($this->bendahara)->post($url, $isi())->assertSessionHasErrors('note');

    expect($kontingen->invoice->fresh()->manualPayments()->count())->toBe(1);
});

it('menolak tagihan kejuaraan lain lewat alamat yang ditukar', function () {
    $lain = Tournament::factory()->create();
    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    $this->actingAs($this->bendahara)
        ->get("/admin/turnamen/{$lain->id}/bendahara/{$kontingen->invoice->id}/bukti/1")
        ->assertNotFound();
});

it('mengekspor rekap keuangan sebagai CSV', function () {
    kontingenBertagihan($this->tournament, $this->kelasC, 'Kontingen Ekspor');

    $this->actingAs($this->bendahara)
        ->get("/admin/turnamen/{$this->tournament->id}/bendahara/export")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});

it('menutup panel bendahara dari official kontingen', function () {
    $official = User::factory()->create();
    $official->syncRoles(['official-kontingen']);

    $kontingen = kontingenBertagihan($this->tournament, $this->kelasC);

    $this->actingAs($official)
        ->post("/admin/turnamen/{$this->tournament->id}/bendahara/{$kontingen->invoice->id}/lunas", [
            'note' => 'Saya bayar sendiri',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertForbidden();
});
