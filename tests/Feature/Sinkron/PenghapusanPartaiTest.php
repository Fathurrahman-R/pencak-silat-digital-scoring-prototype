<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PenerapPaket;
use Illuminate\Support\Facades\DB;

/*
 * Bagan yang dibongkar di node global harus ikut hilang di gelanggang.
 *
 * Membuka kunci bagan lalu menyusunnya ulang adalah alur yang memang ada.
 * Partai lamanya dihapus di node global -- tapi partai itu, karena sudah
 * dijadwalkan, "milik" gelanggangnya menurut aturan kepemilikan, dan penerap
 * menolak menghapus baris miliknya sendiri.
 *
 * Akibatnya di layar: partai yang sudah tidak ada di mana pun tetap berdiri
 * di antrean gelanggang, pointer tayang masih menunjuk ke sana, dan partai
 * sungguhan berikutnya ditolak dengan "masih ada partai berjalan".
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'B',
        'sinkron.node' => 'gelanggang-b',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create(), ['position' => 1]);

        return $r;
    };

    $this->partai = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
        'arena_id' => $this->arenaB->id, 'order_in_arena' => 1,
    ]);

    $this->hapus = fn (string $tabel, int|string $id) => (new PenerapPaket(new Kepemilikan))->terapkan([
        'baris' => [['tabel' => $tabel, 'id' => (string) $id, 'aksi' => CatatanKeluar::HAPUS]],
    ]);
});

it('menghapus partai gelanggangnya sendiri saat node global membongkar bagannya', function () {
    $ringkasan = ($this->hapus)('matches', $this->partai->id);

    expect($ringkasan['dihapus'])->toBe(1)
        ->and(DB::table('matches')->where('id', $this->partai->id)->exists())->toBeFalse();
});

/*
 * Yang dijaga kepemilikan adalah PEMBARUAN. Nilai yang lahir di gelanggang ini
 * tidak boleh dihapus atas perintah node lain -- catatan pertandingannya
 * satu-satunya ada di sini.
 */
it('tetap menolak menghapus nilai yang lahir di gelanggang ini', function () {
    $nilai = ScoreEvent::create([
        'match_id' => $this->partai->id, 'round' => 1, 'corner' => 'red',
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    $ringkasan = ($this->hapus)('score_events', $nilai->id);

    expect($ringkasan['ditolak'])->toBe(1)
        ->and(DB::table('score_events')->where('id', $nilai->id)->exists())->toBeTrue();
});
