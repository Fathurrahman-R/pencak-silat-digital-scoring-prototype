<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PembungkusPaket;
use App\Support\Sinkron\PenerapPaket;
use Illuminate\Support\Facades\DB;

/*
 * Paket sinkron -- yang dikirim antar gelanggang, dan yang ditolak saat tiba.
 *
 * Dua janji yang dijaga berkas ini:
 *
 *   idempoten     paket yang sama boleh diterapkan berkali-kali tanpa
 *                 mengubah hasilnya. Itu yang membuat penarikan yang putus
 *                 di tengah cukup diulang, tanpa ada yang perlu tahu berapa
 *                 banyak yang sempat masuk.
 *
 *   tanpa balik   baris tidak pernah kembali ke pembuatnya lewat jalan
 *                 memutar. Kalau bisa, salinan basi akan menimpa yang asli --
 *                 nilai yang sudah dibatalkan hidup lagi, tepat pada partai
 *                 yang sedang disengketakan.
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

    $this->nilai = fn (SilatMatch $match, string $sudut = 'red') => ScoreEvent::create([
        'match_id' => $match->id, 'round' => 1, 'corner' => $sudut,
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    $this->sebagai = function (string $peran, string $arena) {
        config(['sinkron.peran' => $peran, 'sinkron.arena' => $arena]);

        return new Kepemilikan;
    };
});

it('membungkus nilai yang terbit di gelanggang sendiri', function () {
    $nilai = ($this->nilai)(($this->partai)($this->arenaA, 1));

    $paket = (new PembungkusPaket(new Kepemilikan))->bangun(0);

    $skor = collect($paket['baris'])->firstWhere('tabel', 'score_events');

    expect($paket['node'])->toBe('gelanggang-a')
        ->and($paket['selesai'])->toBeTrue()
        ->and($paket['kursor'])->toBeGreaterThan(0)
        ->and($skor)->not->toBeNull()
        ->and($skor['id'])->toBe((string) $nilai->id)
        ->and($skor['data']['value'])->toBe(1);
});

/*
 * Baris yang berubah berkali-kali terkirim sekali, dalam keadaan terakhirnya.
 * Tanpa pemadatan ini, penerap harus menjaga urutan supaya pembatalan tidak
 * mendahului penerbitan -- masalah yang tidak perlu ada.
 */
it('memadatkan perubahan berulang jadi satu baris berkeadaan terakhir', function () {
    $nilai = ($this->nilai)(($this->partai)($this->arenaA, 2));
    $nilai->forceFill(['voided_at' => now(), 'void_reason' => 'uji'])->save();

    $paket = (new PembungkusPaket(new Kepemilikan))->bangun(0);
    $skor = collect($paket['baris'])->where('tabel', 'score_events');

    expect($skor)->toHaveCount(1)
        ->and($skor->first()['data']['voided_at'])->not->toBeNull();
});

it('tidak membungkus baris milik gelanggang lain', function () {
    ($this->nilai)(($this->partai)($this->arenaB, 3));

    $paket = (new PembungkusPaket(new Kepemilikan))->bangun(0);

    expect(collect($paket['baris'])->where('tabel', 'score_events'))->toBeEmpty();
});

/*
 * Bendera selesai, bukan paket kosong, yang menghentikan perulangan penarik.
 * Paket yang seluruh isinya tersaring habis tetap membawa kursor yang maju,
 * dan berhenti di situ berarti melewatkan sisanya.
 */
it('menandai belum selesai saat catatan lebih banyak dari batas', function () {
    $partai = ($this->partai)($this->arenaA, 4);
    ($this->nilai)($partai);
    ($this->nilai)($partai, 'blue');
    ($this->nilai)($partai);

    $paket = (new PembungkusPaket(new Kepemilikan))->bangun(0, batas: 2);

    expect($paket['selesai'])->toBeFalse();
});

it('menerapkan paket dari peer dan mengulanginya tanpa mengubah hasil', function () {
    $partaiB = ($this->partai)($this->arenaB, 5);

    // Paket seolah datang dari gelanggang B.
    $nilai = ($this->nilai)($partaiB);
    $baris = (array) DB::table('score_events')->where('id', $nilai->id)->first();

    DB::table('score_events')->where('id', $nilai->id)->delete();

    $paket = ['baris' => [[
        'tabel' => 'score_events', 'id' => (string) $nilai->id,
        'aksi' => 'simpan', 'data' => $baris,
    ]]];

    $penerap = new PenerapPaket(($this->sebagai)('gelanggang', 'A'));

    $pertama = $penerap->terapkan($paket);
    $sesudahPertama = (array) DB::table('score_events')->where('id', $nilai->id)->first();

    $kedua = $penerap->terapkan($paket);
    $sesudahKedua = (array) DB::table('score_events')->where('id', $nilai->id)->first();

    expect($pertama['diterapkan'])->toBe(1)
        ->and($kedua['diterapkan'])->toBe(1)
        ->and($sesudahKedua)->toBe($sesudahPertama)
        ->and(DB::table('score_events')->where('id', $nilai->id)->count())->toBe(1);
});

/*
 * Pemutus lingkaran, diuji dari sisi penerimaan. Peer boleh saja mengirimkan
 * kembali baris yang dulu ia terima dari sini; menerimanya berarti menimpa
 * catatan asli dengan salinan yang tertinggal beberapa penarikan.
 */
it('menolak baris yang dimiliki node penerima sendiri', function () {
    $partaiA = ($this->partai)($this->arenaA, 6);
    $nilai = ($this->nilai)($partaiA);

    $baris = (array) DB::table('score_events')->where('id', $nilai->id)->first();
    $baris['value'] = 99; // versi basi yang dikirim balik peer

    $ringkasan = (new PenerapPaket(($this->sebagai)('gelanggang', 'A')))
        ->terapkan(['baris' => [[
            'tabel' => 'score_events', 'id' => (string) $nilai->id,
            'aksi' => 'simpan', 'data' => $baris,
        ]]]);

    expect($ringkasan['ditolak'])->toBe(1)
        ->and($ringkasan['diterapkan'])->toBe(0)
        ->and(DB::table('score_events')->where('id', $nilai->id)->value('value'))->toBe(1);
});

it('menolak penghapusan baris miliknya sendiri', function () {
    $nilai = ($this->nilai)(($this->partai)($this->arenaA, 7));

    $ringkasan = (new PenerapPaket(($this->sebagai)('gelanggang', 'A')))
        ->terapkan(['baris' => [[
            'tabel' => 'score_events', 'id' => (string) $nilai->id, 'aksi' => 'hapus',
        ]]]);

    expect($ringkasan['ditolak'])->toBe(1)
        ->and(DB::table('score_events')->where('id', $nilai->id)->exists())->toBeTrue();
});

it('tidak pernah menerapkan judge_inputs walau dipaksakan ke dalam paket', function () {
    $partaiB = ($this->partai)($this->arenaB, 8);

    $ringkasan = (new PenerapPaket(($this->sebagai)('gelanggang', 'A')))
        ->terapkan(['baris' => [[
            'tabel' => 'judge_inputs', 'id' => 'apa-pun', 'aksi' => 'simpan',
            'data' => ['id' => 'apa-pun', 'match_id' => $partaiB->id, 'round' => 1,
                'corner' => 'red', 'point_type' => 'pukulan', 'server_ts' => now()],
        ]]]);

    expect($ringkasan['dilewati'])->toBe(1)
        ->and($ringkasan['diterapkan'])->toBe(0);
});

/*
 * Penulisan lewat query builder tidak menembakkan observer, jadi snapshot skor
 * harus dibatalkan sendiri. Tanpa ini panel terus menampilkan angka yang
 * dihitung sebelum nilai dari gelanggang lain masuk, dan angka itu tidak akan
 * pernah menyusul sendiri.
 */
it('membatalkan snapshot skor partai yang menerima perubahan', function () {
    $partaiB = ($this->partai)($this->arenaB, 9);
    $nilai = ($this->nilai)($partaiB);
    $baris = (array) DB::table('score_events')->where('id', $nilai->id)->first();

    DB::table('matches')->where('id', $partaiB->id)->update([
        'snapshot_skor' => json_encode(['total' => ['merah' => 0, 'biru' => 0], 'babak' => [], 'teknik' => []]),
        'snapshot_pada' => now(),
    ]);

    (new PenerapPaket(($this->sebagai)('gelanggang', 'A')))
        ->terapkan(['baris' => [[
            'tabel' => 'score_events', 'id' => (string) $nilai->id,
            'aksi' => 'simpan', 'data' => $baris,
        ]]]);

    expect(DB::table('matches')->where('id', $partaiB->id)->value('snapshot_pada'))->toBeNull();
});

/*
 * Foreign key menolak baris yang menunjuk sesuatu yang belum ada. Paket yang
 * sah pun akan gagal di tengah kalau hukuman tiba sebelum partainya.
 */
it('menerapkan induk lebih dulu walau datang belakangan di paket', function () {
    $partaiB = ($this->partai)($this->arenaB, 10);

    $hukuman = Penalty::create([
        'match_id' => $partaiB->id, 'round' => 1, 'corner' => 'red',
        'tier' => 'teguran', 'level' => 1, 'points' => -1, 'violation_level' => 'sedang',
    ]);

    $barisHukuman = (array) DB::table('penalties')->where('id', $hukuman->id)->first();
    $barisPartai = (array) DB::table('matches')->where('id', $partaiB->id)->first();

    DB::table('penalties')->where('id', $hukuman->id)->delete();

    // Hukuman sengaja ditaruh lebih dulu daripada partainya.
    $ringkasan = (new PenerapPaket(($this->sebagai)('gelanggang', 'A')))
        ->terapkan(['baris' => [
            ['tabel' => 'penalties', 'id' => (string) $hukuman->id, 'aksi' => 'simpan', 'data' => $barisHukuman],
            ['tabel' => 'matches', 'id' => (string) $partaiB->id, 'aksi' => 'simpan', 'data' => $barisPartai],
        ]]);

    expect($ringkasan['diterapkan'])->toBe(2)
        ->and(DB::table('penalties')->where('id', $hukuman->id)->exists())->toBeTrue();
});
