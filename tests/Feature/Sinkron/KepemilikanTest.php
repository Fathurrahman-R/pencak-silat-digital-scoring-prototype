<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeVerification;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PetaSinkron;
use Illuminate\Support\Facades\DB;

/*
 * Kepemilikan baris -- aturan yang menggantikan resolusi konflik.
 *
 * Sinkron peer-to-peer tanpa aturan ini menuntut jawaban atas pertanyaan yang
 * tidak punya jawaban benar: kalau dua node mengubah baris yang sama, mana
 * yang menang. Sistem ini tidak menjawabnya, ia membuat keadaannya tidak bisa
 * terjadi. Berkas ini menjaga janji itu tetap ditepati.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = function () use ($kontingen, $kelas) {
        $r = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $r->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $r;
    };

    $this->partai = function (?Arena $arena, int $posisi) use ($bracket, $daftar) {
        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => $posisi,
            'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
            'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
            'arena_id' => $arena?->id, 'order_in_arena' => $arena === null ? null : $posisi,
        ]);
    };

    $this->sebagai = function (string $peran, string $arena) {
        config(['sinkron.peran' => $peran, 'sinkron.arena' => $arena, 'sinkron.node' => 'uji']);

        return new Kepemilikan;
    };

    $this->barisnya = fn (string $tabel, int|string $id) => (array) DB::table($tabel)->where('id', $id)->first();
});

it('memberi tabel kejuaraan hanya kepada node global', function () {
    $atlet = Athlete::factory()->for(Contingent::factory()->for($this->tournament)->create())->create();
    $baris = ($this->barisnya)('athletes', $atlet->id);

    expect(($this->sebagai)('global', '')->milikNodeIni('athletes', $baris))->toBeTrue()
        ->and(($this->sebagai)('gelanggang', 'A')->milikNodeIni('athletes', $baris))->toBeFalse();
});

it('memberi nilai kepada gelanggang tempat partainya berjalan', function () {
    $partaiA = ($this->partai)($this->arenaA, 1);

    $nilai = ScoreEvent::create([
        'match_id' => $partaiA->id, 'round' => 1, 'corner' => 'red',
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    $baris = ($this->barisnya)('score_events', $nilai->id);

    expect(($this->sebagai)('gelanggang', 'A')->milikNodeIni('score_events', $baris))->toBeTrue()
        ->and(($this->sebagai)('gelanggang', 'B')->milikNodeIni('score_events', $baris))->toBeFalse()
        ->and(($this->sebagai)('global', '')->milikNodeIni('score_events', $baris))->toBeFalse();
});

/*
 * Rantai dua langkah. Jawaban verifikasi tidak menyebut partai sama sekali --
 * ia menempel ke verifikasinya, dan verifikasi itulah yang menyebut partai.
 * Kalau penelusuran berhenti di langkah pertama, seluruh tabel ini jadi tak
 * bertuan dan tidak pernah ikut terkirim ke mana pun.
 */
it('menelusuri dua langkah untuk jawaban verifikasi juri', function () {
    $partaiB = ($this->partai)($this->arenaB, 2);

    $verifikasi = JudgeVerification::create([
        'match_id' => $partaiB->id, 'round' => 1, 'jenis' => 'jatuhan',
        'diminta_oleh' => \App\Models\User::factory()->create()->id, 'diminta_at' => now(),
        'status' => JudgeVerification::BERJALAN,
    ]);

    // Kunci dibangkitkan sendiri: penyisipan lewat query builder melewati
    // model, jadi HasUlids tidak berjalan.
    $jawaban = (string) Illuminate\Support\Str::ulid();

    DB::table('judge_verification_answers')->insert([
        'id' => $jawaban,
        'judge_verification_id' => $verifikasi->id,
        'judge_user_id' => \App\Models\User::factory()->create()->id,
        'judge_number' => 1, 'jawaban' => 'ya', 'server_ts' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $baris = ($this->barisnya)('judge_verification_answers', $jawaban);

    expect(($this->sebagai)('gelanggang', 'B')->milikNodeIni('judge_verification_answers', $baris))->toBeTrue()
        ->and(($this->sebagai)('gelanggang', 'A')->milikNodeIni('judge_verification_answers', $baris))->toBeFalse();
});

/*
 * Partai yang sudah dijadwalkan dimiliki gelanggangnya, karena node itulah
 * yang memperbarui status, babak, dan pemenangnya selama pertandingan.
 */
it('memberi partai terjadwal kepada gelanggangnya, bukan node global', function () {
    $baris = ($this->barisnya)('matches', ($this->partai)($this->arenaA, 3)->id);

    expect(($this->sebagai)('gelanggang', 'A')->milikNodeIni('matches', $baris))->toBeTrue()
        ->and(($this->sebagai)('global', '')->milikNodeIni('matches', $baris))->toBeFalse();
});

/*
 * Sebaliknya, partai yang belum ditempatkan di gelanggang mana pun cuma
 * diketahui node global -- dialah yang menyusun bagan dan menjadwalkannya.
 */
it('memberi partai belum terjadwal kepada node global', function () {
    $baris = ($this->barisnya)('matches', ($this->partai)(null, 4)->id);

    expect(($this->sebagai)('global', '')->milikNodeIni('matches', $baris))->toBeTrue()
        ->and(($this->sebagai)('gelanggang', 'A')->milikNodeIni('matches', $baris))->toBeFalse();
});

/*
 * Yang memutus lingkaran. Tanpa ini, perubahan gelanggang A berkeliling lewat
 * B dan kembali ke A dalam keadaan basi, lalu menimpa yang asli.
 */
it('tidak pernah mengakui baris yang bukan miliknya walau dikirim peer', function () {
    $partaiA = ($this->partai)($this->arenaA, 1);

    $nilai = ScoreEvent::create([
        'match_id' => $partaiA->id, 'round' => 1, 'corner' => 'blue',
        'point_type' => 'tendangan', 'value' => 2, 'server_ts' => now(),
    ]);

    $baris = ($this->barisnya)('score_events', $nilai->id);
    $nodeB = ($this->sebagai)('gelanggang', 'B');

    // B menerima baris ini dari A dan menyimpannya. Yang tidak boleh terjadi:
    // B kemudian menganggapnya miliknya dan mengirimkannya kembali sebagai
    // kebenaran terbaru.
    expect($nodeB->milikNodeIni('score_events', $baris))->toBeFalse();
});

it('mengenali node yang memegang lebih dari satu gelanggang', function () {
    $baris = ($this->barisnya)('matches', ($this->partai)($this->arenaB, 5)->id);

    expect(($this->sebagai)('gelanggang', 'A,B')->milikNodeIni('matches', $baris))->toBeTrue();
});

it('mengeluarkan judge_inputs dari daftar sinkron tapi memasukkannya ke arsip', function () {
    expect(PetaSinkron::disinkronkan('judge_inputs'))->toBeFalse()
        ->and(PetaSinkron::tabelArsip())->toContain('judge_inputs')
        ->and(PetaSinkron::disinkronkan('score_events'))->toBeTrue();
});

/*
 * Urutan penerapan harus menghormati foreign key: yang ditunjuk selalu lebih
 * dulu daripada yang menunjuk. Kalau tidak, paket yang sah pun ditolak basis
 * data di tengah penerapan, dan yang tertinggal adalah impor separuh jadi.
 */
it('menyusun urutan terapkan dengan induk selalu mendahului anak', function () {
    $urutan = PetaSinkron::urutanTerapkan();
    $posisi = array_flip($urutan);

    expect($posisi['tournaments'])->toBeLessThan($posisi['arenas'])
        ->and($posisi['arenas'])->toBeLessThan($posisi['matches'])
        ->and($posisi['brackets'])->toBeLessThan($posisi['matches'])
        ->and($posisi['matches'])->toBeLessThan($posisi['score_events'])
        ->and($posisi['judge_verifications'])->toBeLessThan($posisi['judge_verification_answers'])
        ->and($posisi['jurus_performances'])->toBeLessThan($posisi['jurus_scores']);
});
