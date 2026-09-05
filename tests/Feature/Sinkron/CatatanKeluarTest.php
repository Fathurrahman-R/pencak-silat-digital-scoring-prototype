<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeInput;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/*
 * Catatan keluar -- daftar perubahan yang menunggu ditarik peer.
 *
 * Dua hal yang dijaga berkas ini, dan keduanya soal apa yang TIDAK dicatat.
 * Mencatat terlalu sedikit berarti satu nilai tidak pernah sampai ke node
 * lain. Mencatat terlalu banyak berarti baris yang baru diterima dari peer
 * dikirim balik ke pengirimnya dalam keadaan basi.
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $this->partai = fn (Arena $arena, int $posisi) => SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => $posisi,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
        'arena_id' => $arena->id, 'order_in_arena' => $posisi,
    ]);

    $this->catatan = fn (string $tabel) => DB::table('sinkron_keluar')->where('tabel', $tabel)->get();

    $this->nilai = fn (SilatMatch $match, string $sudut = 'red') => ScoreEvent::create([
        'match_id' => $match->id, 'round' => 1, 'corner' => $sudut,
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);
});

it('mencatat nilai yang terbit di gelanggang sendiri', function () {
    $nilai = ($this->nilai)(($this->partai)($this->arenaA, 1));

    $catatan = ($this->catatan)('score_events');

    expect($catatan)->toHaveCount(1)
        ->and($catatan->first()->baris_id)->toBe((string) $nilai->id)
        ->and($catatan->first()->aksi)->toBe('simpan');
});

/*
 * Yang memutus lingkaran, diuji dari sisi pencatatan.
 *
 * Baris milik gelanggang B yang sampai ke sini lewat sinkron tidak boleh
 * masuk antrean keluar. Kalau masuk, ia berkeliling: A mengirimnya ke C
 * sebagai kebenaran versi A, dan C tidak punya cara tahu bahwa yang berhak
 * atas baris itu sebenarnya B.
 */
it('tidak mencatat baris milik gelanggang lain', function () {
    ($this->nilai)(($this->partai)($this->arenaB, 2));

    expect(($this->catatan)('score_events'))->toBeEmpty();
});

/*
 * judge_inputs sengaja tidak ikut sinkron sama sekali -- ia sembilan puluh
 * persen volume basis data, dan gelanggang tetangga tidak berkepentingan atas
 * penekanan tombol mentah gelanggang lain. Jalannya lewat paket arsip.
 */
it('tidak pernah mencatat judge_inputs', function () {
    $partai = ($this->partai)($this->arenaA, 3);

    JudgeInput::create([
        'match_id' => $partai->id, 'round' => 1, 'judge_user_id' => null,
        'corner' => 'red', 'point_type' => 'pukulan', 'server_ts' => now(),
    ]);

    expect(($this->catatan)('judge_inputs'))->toBeEmpty();
});

it('mencatat pembaruan sebagai simpan dan penghapusan sebagai hapus', function () {
    $partai = ($this->partai)($this->arenaA, 4);
    $hukuman = Penalty::create([
        'match_id' => $partai->id, 'round' => 1, 'corner' => 'red',
        'tier' => 'teguran', 'level' => 1, 'points' => -1, 'violation_level' => 'sedang',
    ]);

    $hukuman->forceFill(['voided_at' => now(), 'void_reason' => 'uji'])->save();
    $hukuman->delete();

    expect(($this->catatan)('penalties')->pluck('aksi')->all())
        ->toBe(['simpan', 'simpan', 'hapus']);
});

/*
 * Node gelanggang tidak boleh mengklaim data kejuaraan. Kalau ia mencatatnya,
 * suntingan tak sengaja di laptop gelanggang akan terkirim ke node global dan
 * menimpa daftar peserta yang disusun sekretariat.
 */
it('tidak mencatat data kejuaraan di node gelanggang', function () {
    Athlete::factory()->for(Contingent::factory()->for($this->tournament)->create())->create();

    expect(($this->catatan)('athletes'))->toBeEmpty();
});

it('mencatat data kejuaraan di node global', function () {
    config(['sinkron.peran' => 'global', 'sinkron.arena' => '']);

    Athlete::factory()->for(Contingent::factory()->for($this->tournament)->create())->create();

    expect(($this->catatan)('athletes'))->toHaveCount(1);
});

/*
 * Kursor yang diberikan ke peer harus naik monoton, karena itulah satu-satunya
 * janji yang membuat penarikan bisa dilanjutkan: peer menyimpan angka ini dan
 * menariknya lagi dari situ.
 */
it('menaikkan kursor tiap ada perubahan baru', function () {
    $catatan = app(App\Support\Sinkron\CatatanKeluar::class);
    $partai = ($this->partai)($this->arenaA, 5);

    $awal = $catatan->kursorTerakhir();
    ($this->nilai)($partai);
    $tengah = $catatan->kursorTerakhir();
    ($this->nilai)($partai, 'blue');
    $akhir = $catatan->kursorTerakhir();

    expect($tengah)->toBeGreaterThan($awal)
        ->and($akhir)->toBeGreaterThan($tengah);
});
