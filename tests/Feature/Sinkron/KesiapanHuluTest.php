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
use App\Support\Bagan\KesiapanHulu;
use App\Support\Sinkron\Kepemilikan;

/*
 * Kesiapan partai hulu -- penjaga yang mencegah gelanggang menayangkan partai
 * yang salah satu sudutnya belum diketahui.
 *
 * Bagan tidak berhenti di batas gelanggang: pemenang di gelanggang A naik ke
 * partai yang bisa dijadwalkan di gelanggang B, dan laptop B baru tahu
 * hasilnya setelah ada yang menekan tombol sinkron. Tanpa penjaga ini,
 * pengendali B memanggil pesilat yang belum ditentukan.
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'B',
        'sinkron.node' => 'gelanggang-b',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $this->partai = fn (int $babak, int $posisi, ?Arena $arena, ?string $disahkan = null) => SilatMatch::create([
        'bracket_id' => $this->bracket->id, 'round' => $babak, 'position' => $posisi,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $arena?->id, 'order_in_arena' => $arena === null ? null : $posisi,
        'ratified_at' => $disahkan,
    ]);

    $this->kesiapan = fn () => new KesiapanHulu(new Kepemilikan);
});

it('meloloskan partai babak pertama tanpa memeriksa apa pun', function () {
    $partai = ($this->partai)(1, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($partai))->toBeTrue();
});

/*
 * Inti penjagaannya. Partai final di gelanggang B diisi dua semifinal yang
 * berjalan di gelanggang A; selama hasilnya belum ditarik, sudutnya belum
 * bisa ditentukan.
 */
it('menahan partai yang hulunya di gelanggang lain belum disahkan', function () {
    ($this->partai)(1, 1, $this->arenaA);
    ($this->partai)(1, 2, $this->arenaA);

    $final = ($this->partai)(2, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($final))->toBeFalse()
        ->and(($this->kesiapan)()->belumSiap($final))->toHaveCount(2)
        ->and(($this->kesiapan)()->gelanggangDitunggu($final))->toBe(['Gelanggang A']);
});

it('meloloskan partai begitu hasil hulunya sudah sampai', function () {
    ($this->partai)(1, 1, $this->arenaA, disahkan: now()->toDateTimeString());
    ($this->partai)(1, 2, $this->arenaA, disahkan: now()->toDateTimeString());

    $final = ($this->partai)(2, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($final))->toBeTrue();
});

/*
 * Partai hulu yang berjalan di gelanggang ini sendiri sengaja TIDAK diperiksa.
 * Datanya sudah ada di laptop yang sama; kalau belum disahkan, itu urusan alur
 * pertandingan biasa, dan menolaknya di sini akan memberi pesan yang
 * menyesatkan -- menyuruh menarik sinkron dari diri sendiri.
 */
it('tidak menahan karena partai hulu di gelanggangnya sendiri', function () {
    ($this->partai)(1, 1, $this->arenaB);
    ($this->partai)(1, 2, $this->arenaB);

    $final = ($this->partai)(2, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($final))->toBeTrue();
});

it('tidak menahan karena partai hulu yang belum dijadwalkan ke gelanggang mana pun', function () {
    ($this->partai)(1, 1, null);
    ($this->partai)(1, 2, null);

    $final = ($this->partai)(2, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($final))->toBeTrue();
});

/*
 * Setengah siap tetap belum siap: satu sudut yang kosong sama saja dengan dua
 * kalau pesilatnya yang dipanggil ke matras.
 */
it('menahan walau baru satu dari dua hulu yang belum sampai', function () {
    ($this->partai)(1, 1, $this->arenaA, disahkan: now()->toDateTimeString());
    ($this->partai)(1, 2, $this->arenaA);

    $final = ($this->partai)(2, 1, $this->arenaB);

    expect(($this->kesiapan)()->siap($final))->toBeFalse()
        ->and(($this->kesiapan)()->belumSiap($final))->toHaveCount(1);
});

/*
 * Aritmetika letak hulu adalah kebalikan PromosiPemenang: partai nomor p babak
 * r diisi partai 2p-1 dan 2p dari babak r-1. Kalau terbalik, penjaga ini akan
 * memeriksa partai yang salah -- dan menahan atau meloloskan tanpa hubungan
 * dengan kenyataan.
 */
it('memeriksa dua partai hulu yang benar untuk posisi kedua', function () {
    ($this->partai)(1, 1, $this->arenaA, disahkan: now()->toDateTimeString());
    ($this->partai)(1, 2, $this->arenaA, disahkan: now()->toDateTimeString());
    $hulu3 = ($this->partai)(1, 3, $this->arenaA);
    $hulu4 = ($this->partai)(1, 4, $this->arenaA);

    $partai = ($this->partai)(2, 2, $this->arenaB);

    expect(($this->kesiapan)()->belumSiap($partai)->pluck('id')->all())
        ->toBe([$hulu3->id, $hulu4->id]);
});
