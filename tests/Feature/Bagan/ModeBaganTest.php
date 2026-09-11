<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\ModeBagan;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PohonBagan;
use App\Support\Bagan\PromosiPemenang;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Dua mode penyusunan bagan Tanding.
 *
 *   GUGUR      ukuran bagan dibulatkan ke pangkat dua terdekat, dan tempat
 *              yang tersisa jadi bye yang disebar susunan unggulan baku.
 *              Bentuk yang dipakai kejuaraan resmi.
 *
 *   PEMASALAN  tidak dibulatkan sama sekali. Peserta dipasangkan berurutan
 *              dari tempat undian, dan kalau jumlahnya ganjil, peserta di
 *              TEMPAT TERAKHIR melenggang ke babak berikutnya. Akibatnya
 *              seluruh peserta bertanding di babak pertama -- tidak ada
 *              separuh bagan yang melenggang tanpa naik gelanggang, yang
 *              memang jadi keluhan tiap kali bagan 2^n dipakai untuk
 *              kejuaraan pemasalan usia dini.
 *
 * Satu hal yang PERLU diketahui panitia dan sengaja tidak disembunyikan:
 * pada jumlah peserta ganjil, tempat terakhir bisa melenggang lebih dari
 * sekali (9 peserta: melenggang di babak 1, 2, dan 3, lalu bertanding sekali
 * di final). Itu konsekuensi langsung dari aturan "selalu tempat terakhir",
 * dan undian acak-lah yang menentukan siapa yang menempatinya.
 */

beforeEach(function () {
    // Dua uji terakhir menembus panel bagan, jadi peran dan resource key-nya
    // harus ada; sisanya murni domain.
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->generator = new BracketGenerator;

    $this->daftarkan = function (int $jumlah) {
        foreach (range(1, $jumlah) as $nomor) {
            $registrasi = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => $this->kelas->id]);

            $registrasi->athletes()->attach(
                Athlete::factory()->for($this->kontingen)->create(['name' => "Pesilat {$nomor}"]),
            );
        }
    };
});

it('memakai mode gugur sebagai bawaan, dengan ukuran pangkat dua', function () {
    ($this->daftarkan)(5);

    $bracket = $this->generator->untukKelas($this->kelas);

    expect($bracket->mode)->toBe(ModeBagan::Gugur)
        ->and($bracket->size)->toBe(8);
});

it('menyusun bagan pemasalan seukuran jumlah pesertanya, tanpa dibulatkan', function (int $peserta, int $babak) {
    ($this->daftarkan)($peserta);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);

    expect($bracket->mode)->toBe(ModeBagan::Pemasalan)
        ->and($bracket->size)->toBe($peserta)
        ->and($bracket->jumlahBabak())->toBe($babak)
        ->and($bracket->slots()->count())->toBe($peserta);
})->with([
    [6, 3],
    [9, 4],
    [10, 4],
    [16, 4],
]);

/*
 * Inti perbedaannya: pada bagan pemasalan genap, TIDAK ADA satu pun bye.
 * Bagan gugur untuk jumlah peserta yang sama menyisakan bye sebanyak selisih
 * ke pangkat dua berikutnya.
 */
it('tidak menyisakan bye sama sekali saat jumlah pesertanya genap', function () {
    ($this->daftarkan)(10);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);

    $babakPertama = $bracket->matches()->where('round', 1)->get();

    expect($babakPertama)->toHaveCount(5)
        ->and($babakPertama->every(fn (SilatMatch $p) => $p->red_registration_id !== null
            && $p->blue_registration_id !== null))->toBeTrue()
        ->and($bracket->matches()->where('win_reason', 'bye')->count())->toBe(0);
});

it('meluluskan peserta di tempat terakhir saat jumlah satu babak ganjil', function () {
    ($this->daftarkan)(9);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);

    $tempatTerakhir = $bracket->slots()->where('position', 9)->firstOrFail();

    $babakPertama = $bracket->matches()->where('round', 1)->get();
    $melenggang = $babakPertama->firstWhere('win_reason', 'bye');

    expect($babakPertama)->toHaveCount(5)
        ->and($melenggang->position)->toBe(5)
        ->and($melenggang->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($melenggang->winner_registration_id)->toBe($tempatTerakhir->registration_id)
        ->and($melenggang->blue_registration_id)->toBeNull();
});

/*
 * Partai yang benar-benar dipertandingkan selalu berjumlah peserta dikurangi
 * satu -- sifat setiap sistem gugur, mode apa pun. Yang berbeda hanya berapa
 * banyak partai bye yang menemaninya.
 */
it('menyisakan tepat satu pemenang lewat partai sebanyak peserta dikurangi satu', function (int $peserta) {
    ($this->daftarkan)($peserta);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);
    $promosi = new PromosiPemenang;

    /*
     * Baganya dimainkan sampai habis, bukan dihitung dari barisnya.
     *
     * Partai yang satu sudutnya tidak akan pernah terisi baru bisa dikenali
     * sesudah penghuninya naik -- di babak dua ke atas, penghuni itu adalah
     * pemenang yang belum ada saat bagan disusun. Menghitung baris partai di
     * basis data karena itu tidak menjawab pertanyaannya; yang menjawab adalah
     * memainkannya.
     */
    $dipertandingkan = 0;

    while (true) {
        $siap = $bracket->matches()
            ->whereNull('winner_registration_id')
            ->whereNotNull('red_registration_id')
            ->whereNotNull('blue_registration_id')
            ->orderBy('round')->orderBy('position')
            ->first();

        if ($siap === null) {
            break;
        }

        $siap->update([
            'winner_registration_id' => $siap->red_registration_id,
            'win_reason' => 'angka',
            'status' => SilatMatch::STATUS_SELESAI,
        ]);

        $dipertandingkan++;
        $promosi($siap->refresh());
    }

    $final = $bracket->matches()->where('round', $bracket->jumlahBabak())->firstOrFail();

    expect($dipertandingkan)->toBe($peserta - 1)
        ->and($final->fresh()->winner_registration_id)->not->toBeNull();
})->with([6, 7, 9, 10, 11, 16]);

/*
 * Peserta yang melenggang tidak boleh berhenti di tengah bagan.
 *
 * Babak berikutnya bisa ikut berjumlah ganjil, dan tempat terakhirnya adalah
 * orang yang barusan melenggang. Kalau perambatan itu tidak dijalankan saat
 * bagan disusun, ada satu partai yang menunggu lawan yang tidak akan pernah
 * datang -- dan gelanggang berhenti menunggunya.
 */
it('merambatkan peserta yang melenggang sampai babak yang punya lawan', function () {
    ($this->daftarkan)(9);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);
    $melenggang = $bracket->slots()->where('position', 9)->firstOrFail()->registration_id;

    // 9 peserta: babak 1 lima partai (satu bye), babak 2 tiga partai (satu
    // bye), babak 3 dua partai (satu bye), final. Yang melenggang berdiri di
    // final tanpa satu pun lawan yang belum ada.
    $final = $bracket->matches()->where('round', $bracket->jumlahBabak())->firstOrFail();

    expect($bracket->matches()->where('round', 2)->count())->toBe(3)
        ->and($bracket->matches()->where('round', 3)->count())->toBe(2)
        ->and($final->red_registration_id === $melenggang || $final->blue_registration_id === $melenggang)->toBeTrue();
});

it('menggambar pohon bagan pemasalan tanpa melempar galat', function (int $peserta) {
    ($this->daftarkan)($peserta);

    $bracket = $this->generator->untukKelas($this->kelas, acak: false, mode: ModeBagan::Pemasalan);

    $pohon = (new PohonBagan)($bracket->load('slots.registration.athletes', 'slots.registration.contingent', 'matches'));

    expect($pohon['kolom'])->toHaveCount($bracket->jumlahBabak())
        ->and($pohon['tinggi'])->toBeGreaterThan(0);
})->with([6, 9, 10]);

it('menolak mode yang tidak dikenal lewat panel bagan', function () {
    ($this->daftarkan)(4);

    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->actingAs($admin)
        ->post(route('admin.turnamen.bagan.susun', [$this->tournament, $this->kelas]), ['mode' => 'setengah-kompetisi'])
        ->assertSessionHasErrors('mode');
});

it('menyusun bagan pemasalan lewat panel bagan', function () {
    ($this->daftarkan)(6);

    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->actingAs($admin)
        ->post(route('admin.turnamen.bagan.susun', [$this->tournament, $this->kelas]), ['mode' => 'pemasalan'])
        ->assertRedirect();

    expect($this->kelas->bracket()->firstOrFail()->mode)->toBe(ModeBagan::Pemasalan);
});
