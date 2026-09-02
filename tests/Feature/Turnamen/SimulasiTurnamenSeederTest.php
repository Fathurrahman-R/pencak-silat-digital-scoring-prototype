<?php

use App\Enums\GolonganUsia;
use App\Enums\StatusInvoice;
use App\Enums\StatusPendaftaran;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Invoice;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightClass;
use App\Models\WeightIn;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Database\Seeders\SimulasiTurnamenSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder simulasi adalah satu-satunya jalan masuk uji coba manual: kalau ia
 * gagal, tidak ada data untuk menguji apa pun. Ia juga memanggil kelas domain
 * yang sama dengan controller (DaftarkanPeserta, InvoiceBuilder,
 * BracketGenerator, PenjadwalPartai), jadi test ini sekaligus penjaga supaya
 * perubahan aturan domain tidak diam-diam mematahkan panduan setup.
 */
beforeEach(function () {
    Storage::fake('local');

    /*
     * Seeder peran dipanggil eksplisit di sini, bukan diandalkan dari
     * callOnce di dalam SimulasiTurnamenSeeder: daftar "sudah pernah
     * dipanggil" milik Laravel bersifat statis dan bertahan antar test,
     * sementara RefreshDatabase mengosongkan tabelnya tiap test.
     */
    $this->seed([
        ResourceSeeder::class,
        RoleSeeder::class,
        SilatResourceSeeder::class,
        SilatRoleSeeder::class,
        SimulasiTurnamenSeeder::class,
    ]);

    $this->tournament = Tournament::where('slug', SimulasiTurnamenSeeder::SLUG)->firstOrFail();
});

it('menyusun kejuaraan simulasi lengkap dengan dua gelanggang beroperator', function () {
    $gelanggang = Arena::where('tournament_id', $this->tournament->id)->orderBy('sort_order')->get();

    expect($gelanggang)->toHaveCount(2);

    $gelanggang->each(function (Arena $arena) {
        expect($arena->operators()->count())->toBe(1);
    });

    /*
     * Jendela konsensus mengikuti bawaan, tidak dilebarkan seeder. Data
     * simulasi yang diam-diam bermain dengan setelan waktu membuat layar
     * gelanggang berperilaku berbeda dari kejuaraan sungguhan -- termasuk
     * berapa lama indikator juri menyala.
     */
    expect($this->tournament->peraturan()->window_konsensus_ms)
        ->toBe(config('scoring.juri.tanding.window_ms'))
        ->toBe(2000);
});

it('membuat akun untuk tiap peran gelanggang dengan kata sandi bawaan', function () {
    $wajib = [
        'ketua@silat.test' => 'ketua-pertandingan',
        'operator@silat.test' => 'operator-it',
        'operator2@silat.test' => 'operator-it',
        'wasit1@silat.test' => 'wasit',
        'juri1@silat.test' => 'juri',
        'juri6@silat.test' => 'juri',
        'pengawas@silat.test' => 'pengawas-wasit-juri',
        'komisi@silat.test' => 'wasit-komisi-protes',
        'sekretariat@silat.test' => 'sekretariat',
        'official1@silat.test' => 'official-kontingen',
        'official10@silat.test' => 'official-kontingen',
    ];

    foreach ($wajib as $email => $peran) {
        $user = User::where('email', $email)->first();

        expect($user)->not->toBeNull("akun {$email} tidak dibuat")
            ->and($user->hasRole($peran))->toBeTrue("akun {$email} bukan {$peran}")
            // Tanpa email_verified_at seluruh panel membalas 403.
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }
});

it('menyelesaikan seluruh gerbang pra-acara: tagihan lunas, pendaftaran terverifikasi, timbang badan', function () {
    $kontingen = $this->tournament->contingents()->pluck('id');

    expect($kontingen)->toHaveCount(10);

    $tagihan = Invoice::whereIn('contingent_id', $kontingen)->get();

    expect($tagihan)->toHaveCount(10)
        ->and($tagihan->every(fn (Invoice $i) => $i->status === StatusInvoice::Lunas))->toBeTrue()
        ->and($tagihan->every(fn (Invoice $i) => $i->manualPayments()->exists()))->toBeTrue();

    $pendaftaran = Registration::whereIn('contingent_id', $kontingen)->get();

    expect($pendaftaran)->not->toBeEmpty()
        ->and($pendaftaran->every(fn (Registration $r) => $r->status === StatusPendaftaran::Terverifikasi))->toBeTrue();

    // Hanya peserta Tanding yang ditimbang; nomor Jurus tidak punya kelas berat.
    $tanding = $pendaftaran->whereNotNull('weight_class_id');

    expect(WeightIn::whereIn('registration_id', $tanding->pluck('id'))->count())
        ->toBe($tanding->count())
        ->and(WeightIn::whereIn('registration_id', $tanding->pluck('id'))->where('passed', false)->count())
        ->toBe(0);
});

/*
 * Ukuran datanya bagian dari gunanya. Simulasi empat peserta tidak pernah
 * memperlihatkan halaman jadwal yang panjang, bagan yang harus digulir, atau
 * daftar verifikasi yang tidak muat satu layar — dan itulah keadaan yang
 * dipakai panitia sungguhan.
 */
it('menurunkan 100 pesilat ke kelas A sampai E, sepuluh putra dan sepuluh putri tiap kelas', function () {
    $kontingen = $this->tournament->contingents()->pluck('id');
    $atlet = Athlete::whereIn('contingent_id', $kontingen)->get();

    expect($atlet)->toHaveCount(100)
        // Nama kembar membuat panel verifikasi dan bagan mustahil dibaca.
        ->and($atlet->pluck('name')->unique())->toHaveCount(100);

    $kelas = WeightClass::where('tournament_id', $this->tournament->id)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->whereIn('code', ['A', 'B', 'C', 'D', 'E'])
        ->get();

    expect($kelas)->toHaveCount(10);

    foreach ($kelas as $satu) {
        expect($satu->registrations()->count())
            ->toBe(10, "{$satu->name} {$satu->jenis_kelamin->value} tidak berisi sepuluh peserta");
    }

    // Seluruhnya Tanding: tidak ada satu pun pendaftaran nomor Jurus.
    expect(Registration::whereIn('contingent_id', $kontingen)->whereNotNull('jurus_event_id')->count())->toBe(0);
});

it('mengunci bagan dan menjadwalkan partai babak pertama beserta aparatnya', function () {
    $bagan = Bracket::whereHas('weightClass', fn ($q) => $q->where('tournament_id', $this->tournament->id))->get();

    expect($bagan)->toHaveCount(10)
        ->and($bagan->every(fn (Bracket $b) => $b->locked_at !== null))->toBeTrue()
        // Sepuluh peserta jatuh ke bagan 16; enam tempat sisanya jadi bye.
        ->and($bagan->pluck('size')->unique()->all())->toBe([16]);

    $partai = SilatMatch::whereIn('bracket_id', $bagan->pluck('id'))->get()
        ->filter(fn (SilatMatch $m) => $m->siapDipertandingkan());

    expect($partai)->not->toBeEmpty();

    $jumlahJuri = $this->tournament->peraturan()->jumlah_juri_tanding;

    foreach ($partai as $satu) {
        expect($satu->arena_id)->not->toBeNull("partai #{$satu->id} tidak dijadwalkan")
            ->and($satu->order_in_arena)->not->toBeNull()
            ->and($satu->officials()->where('role', MatchOfficial::ROLE_WASIT)->count())->toBe(1)
            ->and($satu->officials()->where('role', MatchOfficial::ROLE_JURI)->count())->toBe($jumlahJuri);

        // Satu orang tidak boleh merangkap dua kursi di partai yang sama.
        $orang = $satu->officials()->pluck('user_id');
        expect($orang->unique())->toHaveCount($orang->count());
    }
});

it('berhenti dengan peringatan kalau kejuaraan simulasi sudah ada', function () {
    $this->artisan('silat:simulasi')
        ->expectsOutputToContain('sudah ada')
        ->assertFailed();

    expect(Tournament::where('slug', SimulasiTurnamenSeeder::SLUG)->count())->toBe(1);
});

it('menyusun ulang dari bersih lewat --reset', function () {
    $lama = $this->tournament->id;

    $this->artisan('silat:simulasi --reset')
        ->expectsConfirmation('Lanjutkan?', 'yes')
        ->assertSuccessful();

    $baru = Tournament::withTrashed()->where('slug', SimulasiTurnamenSeeder::SLUG)->firstOrFail();

    expect($baru->id)->not->toBe($lama)
        ->and(Tournament::withTrashed()->where('slug', SimulasiTurnamenSeeder::SLUG)->count())->toBe(1)
        ->and($baru->contingents()->count())->toBe(10);
});
