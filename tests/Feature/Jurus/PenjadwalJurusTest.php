<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusBattle;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\PenjadwalJurus;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Gelanggang\PointerTayang;

/*
 * Rantai Jurus ke gelanggang putus di HULU sebelum berkas ini ada.
 *
 * `jurus_performances.arena_id` dan `jurus_battles.arena_id` sudah ada sejak
 * migrasi pertamanya, pointer gelanggang sudah polimorfik, panel Jurus sudah
 * beralamat gelanggang -- tapi tidak satu pun permukaan yang MENULIS kolom itu.
 * Akibatnya antrean Jurus di panel kendali selalu kosong, tombol Tayangkan
 * tidak pernah tergambar, dan semua orang jatuh kembali ke alamat per
 * penampilan. Uji pertama di bawah ini yang menyatakannya.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->arena1 = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 1']);
    $this->arena2 = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 2']);

    $this->nomor = JurusEvent::where('tournament_id', $this->tournament->id)
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $this->nomor->update(['format' => FormatJurus::Battle]);

    $this->daftarkan = function (int $jumlah) {
        return collect(range(1, $jumlah))->map(function () {
            $reg = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => null, 'jurus_event_id' => $this->nomor->id]);
            $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

            return $reg->refresh();
        });
    };

    $this->susun = new SusunBaganJurus;
    $this->penjadwal = new PenjadwalJurus($this->susun);

    /** Satu penampilan lepas, tanpa battle -- bentuk nomor berformat penampilan. */
    $this->buatPenampilan = function (): JurusPerformance {
        $reg = ($this->daftarkan)(1)->first();

        return JurusPerformance::create([
            'jurus_event_id' => $this->nomor->id,
            'registration_id' => $reg->id,
            'tahap' => 'final',
        ]);
    };
});

it('menjadwalkan penampilan ke gelanggang dengan urutan tayang otomatis', function () {
    $penampilan = ($this->buatPenampilan)();

    $hasil = $this->penjadwal->tetapkan($penampilan, $this->arena1);

    expect($hasil->arena_id)->toBe($this->arena1->id)
        ->and($hasil->order_in_arena)->toBe(1);
});

it('menambah urutan tayang di akhir antrean gelanggang yang sudah terisi', function () {
    $satu = ($this->buatPenampilan)();
    $dua = ($this->buatPenampilan)();

    $this->penjadwal->tetapkan($satu, $this->arena1);

    expect($this->penjadwal->tetapkan($dua, $this->arena1)->order_in_arena)->toBe(2);
});

/*
 * Inilah uji yang menutup celah §1: sebelum PenjadwalJurus ada, tidak ada jalan
 * apa pun untuk membuat antrean ini berisi.
 */
it('membuat antrean Jurus panel kendali akhirnya berisi', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);

    $antrean = app(PointerTayang::class)->antreanJurus($this->arena1->refresh());

    expect($antrean)->toHaveCount(1)
        ->and($antrean->first()->id)->toBe($penampilan->id);
});

it('menayangkan penampilan yang sudah dijadwalkan, yang sebelumnya ditolak pointer', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);

    $pengendali = User::factory()->create();
    app(PointerTayang::class)->tunjukPenampilan($this->arena1, $penampilan->refresh(), $pengendali);

    expect(app(PointerTayang::class)->penampilanAktif($this->arena1->refresh())?->id)->toBe($penampilan->id);
});

/** Pasal 12.1.d.7: penampilan pertama dilakukan Pesilat sudut BIRU. */
it('menjadwalkan satu battle sebagai dua penampilan berurutan, biru lebih dulu', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->penjadwal->tetapkanBattle($battle, $this->arena1);

    $antrean = JurusPerformance::where('arena_id', $this->arena1->id)->orderBy('order_in_arena')->get();

    expect($antrean)->toHaveCount(2)
        ->and($antrean->first()->sudut)->toBe('biru')
        ->and($antrean->first()->order_in_arena)->toBe(1)
        ->and($antrean->last()->sudut)->toBe('merah')
        ->and($antrean->last()->order_in_arena)->toBe(2);
});

/*
 * SusunBaganJurus::siapkanPenampilan() menyalin `arena_id` battle ke penampilan
 * yang dibuatnya. Kalau baris battle tidak ikut ditulis, penampilan ronde
 * berikutnya lahir tanpa gelanggang dan antrean berhenti sendiri di semifinal.
 */
it('menulis gelanggang ke baris battle supaya ronde berikutnya mewarisinya', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $hasil = $this->penjadwal->tetapkanBattle($battle, $this->arena1);

    expect($hasil->arena_id)->toBe($this->arena1->id)
        ->and($hasil->order_in_arena)->toBe(1);
});

it('menolak menjadwalkan battle yang sudutnya belum lengkap', function () {
    ($this->daftarkan)(3);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $bye = $bagan->battles()->where('round', 1)->get()
        ->first(fn (JurusBattle $b) => $b->red_registration_id === null || $b->blue_registration_id === null);

    expect(fn () => $this->penjadwal->tetapkanBattle($bye, $this->arena1))
        ->toThrow(RuntimeException::class, 'belum punya dua sudut');
});

it('melepas penampilan dari gelanggangnya', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);

    $hasil = $this->penjadwal->lepas($penampilan->refresh());

    expect($hasil->arena_id)->toBeNull()
        ->and($hasil->order_in_arena)->toBeNull();
});

it('menolak melepas penampilan yang sedang ditayangkan gelanggangnya', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);

    app(PointerTayang::class)->tunjukPenampilan($this->arena1, $penampilan->refresh(), User::factory()->create());

    expect(fn () => $this->penjadwal->lepas($penampilan->refresh()))
        ->toThrow(RuntimeException::class, 'sedang ditayangkan');
});

/*
 * Penjagaannya harus menyebut `tayang_type`. Tanpa itu, gelanggang yang
 * menayangkan partai Tanding bernomor sama akan menahan penampilan yang
 * sebenarnya bebas -- dua tabel, dua penomoran yang berdiri sendiri.
 */
it('tidak keliru menahan penampilan yang nomornya sama dengan partai yang tayang', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);

    $this->arena1->tayang()->create([
        'tayang_type' => 'tanding',
        'tayang_id' => $penampilan->id,
    ]);

    expect($this->penjadwal->lepas($penampilan->refresh())->arena_id)->toBeNull();
});

it('menolak menjadwalkan ulang penampilan yang sudah disahkan', function () {
    $penampilan = ($this->buatPenampilan)();
    $penampilan->update(['ratified_at' => now()]);

    expect(fn () => $this->penjadwal->tetapkan($penampilan->refresh(), $this->arena1))
        ->toThrow(RuntimeException::class, 'sudah disahkan');
});

it('menolak menggeser penampilan yang sedang berlangsung', function () {
    $penampilan = ($this->buatPenampilan)();
    $this->penjadwal->tetapkan($penampilan, $this->arena1);
    $penampilan->refresh()->update(['status' => JurusPerformance::STATUS_BERLANGSUNG]);

    expect(fn () => $this->penjadwal->urutkan($penampilan->refresh(), -1))
        ->toThrow(RuntimeException::class, 'sedang berlangsung');
});

it('menukar urutan penampilan dengan tetangganya', function () {
    $satu = ($this->buatPenampilan)();
    $dua = ($this->buatPenampilan)();

    $this->penjadwal->tetapkan($satu, $this->arena1);
    $this->penjadwal->tetapkan($dua, $this->arena1);

    $hasil = $this->penjadwal->urutkan($dua->refresh(), -1);

    expect($hasil->order_in_arena)->toBe(1)
        ->and($satu->refresh()->order_in_arena)->toBe(2);
});

it('memindahkan penampilan ke urutan tertentu sekali jalan', function () {
    $baris = collect(range(1, 4))->map(function () {
        $satu = ($this->buatPenampilan)();

        return $this->penjadwal->tetapkan($satu, $this->arena1);
    });

    $this->penjadwal->pindahkan($baris->last()->refresh(), 1);

    $urut = JurusPerformance::where('arena_id', $this->arena1->id)
        ->orderBy('order_in_arena')->pluck('id')->all();

    expect($urut)->toBe([
        $baris[3]->id, $baris[0]->id, $baris[1]->id, $baris[2]->id,
    ]);
});

it('merapatkan kembali urutan yang berlubang sesudah satu baris dilepas', function () {
    $baris = collect(range(1, 3))->map(function () {
        return $this->penjadwal->tetapkan(($this->buatPenampilan)(), $this->arena1);
    });

    $this->penjadwal->lepas($baris[1]->refresh());
    $this->penjadwal->pindahkan($baris[2]->refresh(), 1);

    expect(JurusPerformance::where('arena_id', $this->arena1->id)
        ->orderBy('order_in_arena')->pluck('order_in_arena')->all())->toBe([1, 2]);
});

it('melepas kedua sudut satu battle sekaligus', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->penjadwal->tetapkanBattle($battle, $this->arena1);
    $hasil = $this->penjadwal->lepasBattle($battle->refresh());

    expect($hasil->arena_id)->toBeNull()
        ->and(JurusPerformance::where('jurus_battle_id', $battle->id)->whereNotNull('arena_id')->count())->toBe(0);
});

it('memindahkan battle ke gelanggang lain, dua sudutnya sekalian', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->penjadwal->tetapkanBattle($battle, $this->arena1);
    $this->penjadwal->tetapkanBattle($battle->refresh(), $this->arena2);

    expect(JurusPerformance::where('jurus_battle_id', $battle->id)->pluck('arena_id')->unique()->all())
        ->toBe([$this->arena2->id]);
});
