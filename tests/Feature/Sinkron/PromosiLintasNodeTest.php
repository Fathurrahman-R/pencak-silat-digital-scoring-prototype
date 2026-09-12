<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\PromosiPemenang;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PembungkusPaket;
use App\Support\Sinkron\PenerapPaket;
use Illuminate\Support\Facades\DB;

/*
 * Pemenang yang naik melintasi batas gelanggang.
 *
 * Partai babak satu dimainkan di gelanggang A, partai babak duanya
 * dijadwalkan di gelanggang B. Laptop A menaikkan pemenangnya ke partai babak
 * dua -- tapi baris itu milik B, jadi A tidak mencatatnya untuk dikirim, dan B
 * menolaknya kalau pun terkirim. Satu-satunya yang sampai ke B adalah hasil
 * partai babak satu.
 *
 * Kalau B tidak menurunkan sendiri siapa yang naik dari hasil itu, sudut
 * partai babak duanya kosong selamanya -- sementara KesiapanHulu melihat hulu
 * yang sudah disahkan dan menyatakan partainya siap ditayangkan.
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.potongan' => 500,
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $this->hulu = SilatMatch::create([
        'bracket_id' => $this->bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
        'arena_id' => $this->arenaA->id, 'order_in_arena' => 1,
    ]);

    $this->hilir = fn (?Arena $arena) => SilatMatch::create([
        'bracket_id' => $this->bracket->id, 'round' => 2, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $arena?->id, 'order_in_arena' => $arena === null ? null : 1,
    ]);

    app(CatatanKeluar::class)->semai();
    $this->awal = app(CatatanKeluar::class)->kursorTerakhir();

    /*
     * Gelanggang A mengakhiri partainya lewat jalan yang sama dengan
     * MatchTimer::akhiri -- pemenang ditulis, lalu dinaikkan.
     */
    $this->akhiriDiA = function () {
        $this->hulu->update([
            'winner_registration_id' => $this->hulu->red_registration_id,
            'win_reason' => 'angka',
            'status' => SilatMatch::STATUS_SELESAI,
            'ratified_at' => now(),
        ]);

        (new PromosiPemenang)($this->hulu->refresh());

        return app(PembungkusPaket::class)->bangun($this->awal);
    };

    /*
     * Satu basis data memerankan dua laptop: salinan B atas partai babak dua
     * dikembalikan ke keadaan sebelum pemenang naik, karena laptop B yang
     * sesungguhnya tidak pernah menerima tulisan A.
     */
    $this->terimaDiB = function (array $paket, SilatMatch $hilir) {
        DB::table('matches')->where('id', $hilir->id)->update(['red_registration_id' => null]);

        config(['sinkron.arena' => 'B', 'sinkron.node' => 'gelanggang-b']);
        app()->forgetInstance(Kepemilikan::class);
        app()->forgetInstance(CatatanKeluar::class);

        return (new PenerapPaket(new Kepemilikan))->terapkan($paket);
    };
});

it('mengisi sudut partai babak dua di gelanggang lain dari hasil partai hulunya', function () {
    $hilir = ($this->hilir)($this->arenaB);

    $paket = ($this->akhiriDiA)();
    ($this->terimaDiB)($paket, $hilir);

    expect($hilir->fresh()->red_registration_id)->toBe($this->hulu->red_registration_id);
});

/*
 * Sudut yang terisi di B harus ikut tercatat untuk dikirim: B pemiliknya, dan
 * node global hanya menerima keadaan partai itu dari B.
 */
it('mencatat sudut yang diturunkan itu untuk dikirim ke peer', function () {
    $hilir = ($this->hilir)($this->arenaB);

    $paket = ($this->akhiriDiA)();
    $sebelum = DB::table('sinkron_keluar')->max('id');

    ($this->terimaDiB)($paket, $hilir);

    $tercatat = DB::table('sinkron_keluar')
        ->where('id', '>', $sebelum)
        ->where('tabel', 'matches')
        ->where('baris_id', (string) $hilir->id)
        ->exists();

    expect($tercatat)->toBeTrue();
});

/*
 * Partai babak dua yang belum dijadwalkan dimiliki node global. Node global
 * pun hanya menerima hasil hulunya dari gelanggang A.
 */
it('mengisi sudut partai hilir yang belum dijadwalkan saat hasilnya tiba di node global', function () {
    $hilir = ($this->hilir)(null);

    $paket = ($this->akhiriDiA)();

    DB::table('matches')->where('id', $hilir->id)->update(['red_registration_id' => null]);
    config(['sinkron.peran' => 'global', 'sinkron.arena' => '', 'sinkron.node' => 'global']);
    app()->forgetInstance(Kepemilikan::class);
    app()->forgetInstance(CatatanKeluar::class);

    (new PenerapPaket(new Kepemilikan))->terapkan($paket);

    expect($hilir->fresh()->red_registration_id)->toBe($this->hulu->red_registration_id);
});

/*
 * Topologi yang dianjurkan dokumen: tiap laptop gelanggang cukup mengenal node
 * global. Hasil partai babak satu di A hanya bisa sampai ke B lewat node
 * global -- jadi node global harus meneruskannya, bukan cuma menyimpannya.
 */
it('meneruskan hasil partai dari satu gelanggang ke gelanggang lain lewat node global', function () {
    ($this->hilir)($this->arenaB);

    $paket = ($this->akhiriDiA)();

    config(['sinkron.peran' => 'global', 'sinkron.arena' => '', 'sinkron.node' => 'global']);
    app()->forgetInstance(Kepemilikan::class);
    app()->forgetInstance(CatatanKeluar::class);

    $kursorGlobal = app(CatatanKeluar::class)->kursorTerakhir();

    (new PenerapPaket(new Kepemilikan))->terapkan($paket);

    $diteruskan = collect(app(PembungkusPaket::class)->bangun($kursorGlobal)['baris'])
        ->where('tabel', 'matches')
        ->firstWhere('id', (string) $this->hulu->id);

    expect($diteruskan)->not->toBeNull()
        ->and($diteruskan['data']['winner_registration_id'])->toBe($this->hulu->red_registration_id);
});

/*
 * Menurunkan ulang hasil yang sama tidak boleh menulis apa pun: penarikan
 * diulang setiap kali tombol Tarik ditekan, dan tiap tulisan baru adalah
 * satu catatan keluar yang dikirim berkeliling tanpa isi.
 */
it('tidak menulis ulang sudut yang sudah benar', function () {
    $hilir = ($this->hilir)($this->arenaB);

    $paket = ($this->akhiriDiA)();
    ($this->terimaDiB)($paket, $hilir);

    $sebelum = DB::table('sinkron_keluar')->max('id');

    (new PenerapPaket(new Kepemilikan))->terapkan($paket);

    expect(DB::table('sinkron_keluar')->max('id'))->toBe($sebelum);
});
