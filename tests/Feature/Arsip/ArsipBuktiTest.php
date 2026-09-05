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
use App\Support\Arsip\PaketArsip;
use App\Support\Arsip\PemangkasRiwayatJuri;
use App\Support\Arsip\PendorongArsip;
use App\Support\Arsip\PenerimaArsip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Arsip bukti -- satu-satunya salinan riwayat penekanan tombol juri di luar
 * laptop tempat ia lahir.
 *
 * judge_inputs tidak ikut sinkron peer-to-peer: gelanggang tetangga tidak
 * berkepentingan atas penekanan tombol mentah gelanggang lain, dan tabel itu
 * sembilan puluh persen volume basis data. Padahal justru barisan itulah yang
 * ditanyakan saat hasil digugat. Karena itu pemangkasannya tidak boleh
 * berjalan sebelum ada yang benar-benar memegang salinannya.
 */

beforeEach(function () {
    Storage::fake('local');

    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.token' => 'rahasia-uji',
        'sinkron.peer' => [
            ['nama' => 'global', 'peran' => 'global', 'url' => 'http://node-global.test', 'token' => 'token-global'],
        ],
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_SELESAI, 'current_round' => 3,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
        'ratified_at' => now(),
    ]);

    // Riwayat secukupnya supaya paketnya benar-benar berisi.
    foreach (range(1, 12) as $i) {
        JudgeInput::create([
            'match_id' => $this->match->id, 'round' => 1, 'judge_user_id' => null,
            'corner' => $i % 2 === 0 ? 'red' : 'blue', 'point_type' => 'pukulan',
            'server_ts' => now()->addMilliseconds($i * 400),
        ]);
    }

    ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => 'red',
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    Penalty::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => 'blue',
        'tier' => 'teguran', 'level' => 1, 'points' => -1, 'violation_level' => 'sedang',
    ]);
});

it('membungkus seluruh rantai bukti satu partai', function () {
    $paket = app(PaketArsip::class)->bangun($this->match);

    expect($paket['partai'])->toBe((string) $this->match->id)
        ->and($paket['tabel']['judge_inputs'])->toHaveCount(12)
        ->and($paket['tabel']['score_events'])->toHaveCount(1)
        ->and($paket['tabel']['penalties'])->toHaveCount(1)
        // matches.id tetap integer -- tabel itu sengaja tidak ikut pindah ke
        // ULID karena hanya node global yang menyisipkannya.
        ->and((string) $paket['partai_baris']['id'])->toBe((string) $this->match->id);
});

/*
 * Paket harus bisa dibaca kembali utuh setelah dipadatkan dan dibongkar --
 * kalau tidak, yang tersimpan di node arsip bukan bukti melainkan berkas yang
 * tidak bisa dibuka saat dibutuhkan.
 */
it('bisa dibaca kembali utuh setelah dipadatkan', function () {
    $arsip = app(PaketArsip::class);

    $asli = $arsip->bangun($this->match);
    $kembali = $arsip->bacaKembali($arsip->padatkan($asli));

    expect($kembali)->toBe($asli);
});

it('menandai arsip diterima saat checksum node global cocok', function () {
    $arsip = app(PaketArsip::class);
    $checksum = null;

    Http::fake(function ($request) use ($arsip, &$checksum) {
        $checksum = $arsip->checksum($request->body());

        return Http::response(['checksum' => $checksum, 'versi' => 1], 201);
    });

    $hasil = app(PendorongArsip::class)->dorong($this->match);

    expect($hasil['terkirim'])->toBeTrue()
        ->and(DB::table('arsip_keluar')->where('match_id', $this->match->id)->value('status'))
        ->toBe(PendorongArsip::DITERIMA);
});

/*
 * Checksum yang tidak cocok berarti yang tersimpan di sana BUKAN yang dikirim
 * dari sini. Ditandai gagal, bukan diterima -- kalau tidak, pemangkasan akan
 * membuang bukti sambil bersandar pada salinan yang berbeda isinya.
 */
it('menandai gagal saat checksum node global berbeda', function () {
    Http::fake(fn () => Http::response(['checksum' => str_repeat('0', 64)], 201));

    $hasil = app(PendorongArsip::class)->dorong($this->match);

    expect($hasil['terkirim'])->toBeFalse()
        ->and(DB::table('arsip_keluar')->where('match_id', $this->match->id)->value('status'))
        ->toBe(PendorongArsip::GAGAL);
});

/*
 * Node global yang mati tidak boleh menghentikan pertandingan. Pengesahan
 * tetap berhasil; yang tertinggal cuma satu baris antrean.
 */
it('tidak melempar galat saat node global tidak terjangkau', function () {
    Http::fake(fn () => Http::response('', 500));

    app(PendorongArsip::class)->antrekan($this->match);

    expect(DB::table('arsip_keluar')->where('match_id', $this->match->id)->exists())->toBeTrue()
        ->and(DB::table('arsip_keluar')->where('match_id', $this->match->id)->value('status'))
        ->toBe(PendorongArsip::GAGAL);
});

it('menyimpan paket sebagai berkas beku di node global', function () {
    config(['sinkron.peran' => 'global']);

    $arsip = app(PaketArsip::class);
    $padat = $arsip->padatkan($arsip->bangun($this->match));

    $hasil = app(PenerimaArsip::class)->terima((string) $this->match->id, $padat, $arsip->checksum($padat));

    Storage::disk('local')->assertExists($hasil['jalur']);

    expect($hasil['versi'])->toBe(1)
        ->and($hasil['checksum'])->toBe($arsip->checksum($padat))
        ->and(DB::table('arsip_partai')->where('match_id', $this->match->id)->count())->toBe(1);
});

/*
 * Partai yang sama bisa dikirim ulang setelah babak susulan mengubah hasilnya.
 * Yang lama tidak boleh hilang: justru perubahan itulah yang paling mungkin
 * dipersoalkan, dan menjawabnya butuh kedua keadaan.
 */
it('menyimpan kiriman ulang sebagai versi baru tanpa menimpa yang lama', function () {
    config(['sinkron.peran' => 'global']);

    $arsip = app(PaketArsip::class);
    $penerima = app(PenerimaArsip::class);

    $pertama = $arsip->padatkan($arsip->bangun($this->match));
    $hasil1 = $penerima->terima((string) $this->match->id, $pertama);

    ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 3, 'corner' => 'blue',
        'point_type' => 'jatuhan', 'value' => 3, 'server_ts' => now(),
    ]);

    $kedua = $arsip->padatkan($arsip->bangun($this->match->fresh()));
    $hasil2 = $penerima->terima((string) $this->match->id, $kedua);

    Storage::disk('local')->assertExists($hasil1['jalur']);
    Storage::disk('local')->assertExists($hasil2['jalur']);

    expect($hasil2['versi'])->toBe(2)
        ->and($hasil1['jalur'])->not->toBe($hasil2['jalur'])
        ->and($hasil2['jumlah_baris'])->toBeGreaterThan($hasil1['jumlah_baris']);
});

it('menolak paket yang rusak di perjalanan', function () {
    config(['sinkron.peran' => 'global']);

    $arsip = app(PaketArsip::class);
    $padat = $arsip->padatkan($arsip->bangun($this->match));

    expect(fn () => app(PenerimaArsip::class)->terima((string) $this->match->id, $padat, str_repeat('a', 64)))
        ->toThrow(RuntimeException::class, 'checksum tidak cocok');
});

/*
 * Inti penjagaan pemangkasan: node global HARUS ditanya saat itu juga.
 * Catatan lokal bisa menyebut "diterima" untuk berkas yang sesudahnya
 * terhapus, tertimpa, atau tidak pernah benar-benar tersimpan.
 */
it('menolak memangkas saat node global tidak memegang arsipnya', function () {
    DB::table('arsip_keluar')->insert([
        'match_id' => (string) $this->match->id, 'status' => PendorongArsip::DITERIMA,
        'percobaan' => 1, 'checksum' => str_repeat('a', 64), 'jumlah_baris' => 14,
        'ukuran_bita' => 100, 'dikirim_pada' => now(), 'diterima_pada' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Http::fake(fn () => Http::response(['pesan' => 'Belum ada arsip untuk partai ini.'], 404));

    $hasil = app(PemangkasRiwayatJuri::class)->pangkas();

    expect($hasil['dipangkas'])->toBe(0)
        ->and($hasil['dilewati'])->toHaveCount(1)
        ->and(DB::table('judge_inputs')->where('match_id', $this->match->id)->count())->toBe(12);
});

it('menolak memangkas saat checksum node global berbeda', function () {
    DB::table('arsip_keluar')->insert([
        'match_id' => (string) $this->match->id, 'status' => PendorongArsip::DITERIMA,
        'percobaan' => 1, 'checksum' => str_repeat('a', 64), 'jumlah_baris' => 14,
        'ukuran_bita' => 100, 'dikirim_pada' => now(), 'diterima_pada' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Http::fake(fn () => Http::response(['checksum' => str_repeat('b', 64), 'versi' => 1], 200));

    $hasil = app(PemangkasRiwayatJuri::class)->pangkas();

    expect($hasil['dipangkas'])->toBe(0)
        ->and(DB::table('judge_inputs')->where('match_id', $this->match->id)->count())->toBe(12);
});

it('memangkas dan menandai partai setelah node global mengonfirmasi', function () {
    $checksum = str_repeat('c', 64);

    DB::table('arsip_keluar')->insert([
        'match_id' => (string) $this->match->id, 'status' => PendorongArsip::DITERIMA,
        'percobaan' => 1, 'checksum' => $checksum, 'jumlah_baris' => 14,
        'ukuran_bita' => 100, 'dikirim_pada' => now(), 'diterima_pada' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Http::fake(fn () => Http::response(['checksum' => $checksum, 'versi' => 1, 'jumlah_baris' => 14], 200));

    $hasil = app(PemangkasRiwayatJuri::class)->pangkas();

    expect($hasil['dipangkas'])->toBe(1)
        ->and($hasil['baris_dibuang'])->toBe(12)
        ->and(DB::table('judge_inputs')->where('match_id', $this->match->id)->count())->toBe(0)
        // Penanda inilah yang membuat panel bisa mengatakan rinciannya pindah,
        // bukan menampilkan daftar kosong seolah tidak ada yang menekan tombol.
        ->and($this->match->fresh()->judge_inputs_dipangkas_pada)->not->toBeNull()
        // Yang lain TIDAK ikut terbuang: papan hasil dan rekap membacanya.
        ->and(DB::table('score_events')->where('match_id', $this->match->id)->count())->toBe(1)
        ->and(DB::table('penalties')->where('match_id', $this->match->id)->count())->toBe(1);
});

/*
 * Partai yang sedang ditayangkan tidak disentuh, walau seluruh syarat arsip
 * terpenuhi. Riwayatnya sedang dibaca panel di gelanggang saat itu juga.
 */
it('tidak memangkas partai yang sedang ditayangkan gelanggang', function () {
    $this->arena->forceFill(['active_match_id' => $this->match->id])->save();

    DB::table('arsip_keluar')->insert([
        'match_id' => (string) $this->match->id, 'status' => PendorongArsip::DITERIMA,
        'percobaan' => 1, 'checksum' => str_repeat('c', 64), 'jumlah_baris' => 14,
        'ukuran_bita' => 100, 'dikirim_pada' => now(), 'diterima_pada' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Http::fake(fn () => Http::response(['checksum' => str_repeat('c', 64)], 200));

    expect(app(PemangkasRiwayatJuri::class)->pangkas()['dipangkas'])->toBe(0)
        ->and(DB::table('judge_inputs')->where('match_id', $this->match->id)->count())->toBe(12);
});

it('tidak memangkas partai yang belum disahkan', function () {
    $this->match->forceFill(['ratified_at' => null])->saveQuietly();

    DB::table('arsip_keluar')->insert([
        'match_id' => (string) $this->match->id, 'status' => PendorongArsip::DITERIMA,
        'percobaan' => 1, 'checksum' => str_repeat('c', 64), 'jumlah_baris' => 14,
        'ukuran_bita' => 100, 'dikirim_pada' => now(), 'diterima_pada' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(app(PemangkasRiwayatJuri::class)->pangkas()['dipangkas'])->toBe(0);
});
