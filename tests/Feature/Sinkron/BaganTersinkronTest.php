<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Support\Bagan\BracketGenerator;
use App\Support\Sinkron\CatatanKeluar;
use Illuminate\Support\Facades\DB;

/*
 * Bagan yang disusun SESUDAH laptop gelanggang terpasang.
 *
 * Penyusun bagan menyisipkan partai dan tempatnya sekali jalan lewat
 * `insert()`. Penyisipan massal melewati observer, jadi tidak satu pun
 * barisnya tercatat untuk dikirim: bagan itu hidup di node global saja.
 * Di gelanggang tidak ada galat, cuma kelas yang seolah belum disusun --
 * dan pada penarikan penuh berikutnya (pemasangan node lain) ia muncul,
 * sehingga cacatnya terlihat seperti "kadang sampai, kadang tidak".
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'global',
        'sinkron.node' => 'global',
        'sinkron.arena' => '',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    foreach (range(1, 4) as $n) {
        $daftar = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $this->kelas->id]);

        $daftar->athletes()->attach(Athlete::factory()->for($kontingen)->create(), ['position' => 1]);
    }

    app(CatatanKeluar::class)->semai();
    $this->awal = DB::table('sinkron_keluar')->max('id');
});

it('mencatat seluruh partai bagan yang baru disusun', function () {
    app(BracketGenerator::class)->untukKelas($this->kelas);

    $partai = DB::table('matches')->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();

    $tercatat = DB::table('sinkron_keluar')
        ->where('id', '>', $this->awal)
        ->where('tabel', 'matches')
        ->pluck('baris_id')->unique()->sort()->values();

    expect($partai)->not->toBeEmpty()
        ->and($tercatat->all())->toBe($partai->all());
});

/*
 * Tempat bagan menentukan siapa bertemu siapa. Tanpa catatannya, node
 * gelanggang menerima partai tanpa tahu undiannya.
 */
it('mencatat tempat bagan yang baru disusun', function () {
    app(BracketGenerator::class)->untukKelas($this->kelas);

    $tercatat = DB::table('sinkron_keluar')
        ->where('id', '>', $this->awal)
        ->where('tabel', 'bracket_slots')
        ->count();

    expect($tercatat)->toBe(DB::table('bracket_slots')->count());
});
