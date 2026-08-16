<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusPendaftaran;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PromosiPemenang;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->generator = new BracketGenerator;
});

/** Membuat sejumlah peserta yang sudah disahkan panitia di satu kelas. */
function pesertaSah(Contingent $kontingen, $kelas, int $jumlah): void
{
    foreach (range(1, $jumlah) as $nomor) {
        $registration = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);

        $registration->athletes()->attach(
            Athlete::factory()->for($kontingen)->create(['name' => "Pesilat {$nomor}"]),
        );
    }
}

it('menyusun bagan berukuran pangkat dua terkecil yang memuat pesertanya', function (int $peserta, int $ukuran) {
    pesertaSah($this->kontingen, $this->kelas, $peserta);

    $bracket = $this->generator->untukKelas($this->kelas);

    expect($bracket->size)->toBe($ukuran)
        ->and($bracket->slots()->count())->toBe($ukuran)
        ->and($bracket->slots()->whereNotNull('registration_id')->count())->toBe($peserta);
})->with([
    [2, 2], [3, 4], [5, 8], [8, 8], [9, 16], [16, 16], [17, 32],
]);

it('membuat partai untuk seluruh babak sampai final', function (int $peserta, int $partai) {
    pesertaSah($this->kontingen, $this->kelas, $peserta);

    $bracket = $this->generator->untukKelas($this->kelas);

    expect($bracket->matches()->count())->toBe($partai)
        ->and($bracket->jumlahBabak())->toBe((int) log($bracket->size, 2));
})->with([
    'bagan 2 punya 1 partai' => [2, 1],
    'bagan 4 punya 3 partai' => [3, 3],
    'bagan 8 punya 7 partai' => [5, 7],
    'bagan 16 punya 15 partai' => [9, 15],
]);

it('menolak menyusun bagan yang pesertanya kurang dari dua', function () {
    pesertaSah($this->kontingen, $this->kelas, 1);

    $this->generator->untukKelas($this->kelas);
})->throws(RuntimeException::class, 'sekurang-kurangnya dua');

/*
 * Hanya peserta yang sudah disahkan panitia yang masuk bagan. Yang gugur di
 * timbang badan tidak ikut, dan itu memang dimaui — bagan disusun setelah
 * penimbangan.
 */
it('hanya memasukkan peserta yang sudah disahkan', function () {
    pesertaSah($this->kontingen, $this->kelas, 4);

    Registration::factory()->for($this->kontingen)->diajukan()
        ->create(['weight_class_id' => $this->kelas->id]);
    Registration::factory()->for($this->kontingen)
        ->create(['weight_class_id' => $this->kelas->id, 'status' => StatusPendaftaran::Gugur]);

    $bracket = $this->generator->untukKelas($this->kelas);

    expect($bracket->size)->toBe(4)
        ->and($bracket->slots()->whereNotNull('registration_id')->count())->toBe(4);
});

/*
 * Inti penanganan bye: peserta yang lawannya kosong langsung diluluskan saat
 * bagan disusun. Menyisakannya sebagai partai terjadwal berarti operator
 * gelanggang menunggu sesuatu yang tidak akan datang.
 */
it('meluluskan peserta yang lawannya bye saat bagan disusun', function () {
    pesertaSah($this->kontingen, $this->kelas, 5);

    $bracket = $this->generator->untukKelas($this->kelas);

    $partaiBye = $bracket->matches()->where('round', 1)->get()
        ->filter(fn (SilatMatch $m): bool => $m->win_reason === 'bye');

    expect($partaiBye)->toHaveCount(3);

    foreach ($partaiBye as $partai) {
        expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
            ->and($partai->winner_registration_id)->not->toBeNull();
    }

    // Tiga bye berarti tiga tempat di babak kedua sudah terisi sejak awal.
    $babakDua = $bracket->matches()->where('round', 2)->get();
    $terisi = $babakDua->sum(fn (SilatMatch $m): int => (int) ($m->red_registration_id !== null)
        + (int) ($m->blue_registration_id !== null));

    expect($terisi)->toBe(3);
});

it('tidak menyisakan partai yang kedua sudutnya bye', function (int $peserta) {
    pesertaSah($this->kontingen, $this->kelas, $peserta);

    $bracket = $this->generator->untukKelas($this->kelas);

    $kosongDuanya = $bracket->matches()->where('round', 1)->get()
        ->filter(fn (SilatMatch $m): bool => $m->red_registration_id === null
            && $m->blue_registration_id === null);

    expect($kosongDuanya)->toBeEmpty();
})->with([3, 5, 6, 7, 9, 12, 17]);

it('menaikkan pemenang ke sudut yang benar di babak berikutnya', function () {
    pesertaSah($this->kontingen, $this->kelas, 8);

    $bracket = $this->generator->untukKelas($this->kelas);
    $promosi = new PromosiPemenang;

    // Partai ganjil mengisi sudut merah, partai genap mengisi sudut biru.
    $partaiSatu = $bracket->matches()->where('round', 1)->where('position', 1)->firstOrFail();
    $partaiDua = $bracket->matches()->where('round', 1)->where('position', 2)->firstOrFail();

    $partaiSatu->update(['winner_registration_id' => $partaiSatu->red_registration_id]);
    $partaiDua->update(['winner_registration_id' => $partaiDua->blue_registration_id]);

    $promosi($partaiSatu->refresh());
    $promosi($partaiDua->refresh());

    $semifinal = $bracket->matches()->where('round', 2)->where('position', 1)->firstOrFail();

    expect($semifinal->red_registration_id)->toBe($partaiSatu->red_registration_id)
        ->and($semifinal->blue_registration_id)->toBe($partaiDua->blue_registration_id);
});

it('tidak menaikkan pemenang final ke mana pun', function () {
    pesertaSah($this->kontingen, $this->kelas, 2);

    $bracket = $this->generator->untukKelas($this->kelas);
    $final = $bracket->matches()->where('round', 1)->firstOrFail();

    $final->update(['winner_registration_id' => $final->red_registration_id]);

    expect((new PromosiPemenang)($final->refresh()))->toBeNull();
});

it('menolak menaikkan pemenang dari partai yang belum diputus', function () {
    pesertaSah($this->kontingen, $this->kelas, 4);

    $bracket = $this->generator->untukKelas($this->kelas);

    (new PromosiPemenang)($bracket->matches()->where('round', 1)->firstOrFail());
})->throws(RuntimeException::class, 'belum punya pemenang');

/*
 * Bagan yang bergeser setelah diumumkan berarti kontingen menyiapkan lawan
 * yang keliru — kesalahan yang tidak bisa diperbaiki di hari-H.
 */
it('menolak menyusun ulang bagan yang sudah dikunci', function () {
    pesertaSah($this->kontingen, $this->kelas, 4);

    $bracket = $this->generator->untukKelas($this->kelas);
    $bracket->update(['locked_at' => now()]);

    $this->generator->untukKelas($this->kelas);
})->throws(RuntimeException::class, 'sudah dikunci');

it('mengganti bagan lama yang belum dikunci', function () {
    pesertaSah($this->kontingen, $this->kelas, 4);

    $pertama = $this->generator->untukKelas($this->kelas);
    $kedua = $this->generator->untukKelas($this->kelas);

    expect(Bracket::count())->toBe(1)
        ->and($kedua->id)->not->toBe($pertama->id)
        ->and(SilatMatch::where('bracket_id', $pertama->id)->count())->toBe(0);
});

it('menyebut nama babak sebagaimana dikenal panitia', function () {
    pesertaSah($this->kontingen, $this->kelas, 16);

    $bracket = $this->generator->untukKelas($this->kelas);

    expect($bracket->namaBabak(4))->toBe('Final')
        ->and($bracket->namaBabak(3))->toBe('Semifinal')
        ->and($bracket->namaBabak(2))->toBe('Perempat final')
        ->and($bracket->namaBabak(1))->toBe('Perdelapan final');
});

it('menempatkan tiap peserta tepat sekali di bagan', function () {
    pesertaSah($this->kontingen, $this->kelas, 7);

    $bracket = $this->generator->untukKelas($this->kelas);

    $terpakai = $bracket->slots()->whereNotNull('registration_id')->pluck('registration_id');

    expect($terpakai)->toHaveCount(7)
        ->and($terpakai->unique())->toHaveCount(7);
});
