<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\Sudut;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Scoring\SnapshotSkor;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Support\Facades\DB;

/*
 * Snapshot skor -- angka partai yang sudah dihitung, dibaca tanpa dihitung
 * ulang.
 *
 * Yang dijaga berkas ini cuma satu hal, tapi hal itu yang menentukan snapshot
 * boleh ada atau tidak: isinya tidak pernah berbeda dari hitungan segar
 * TandingScoreCalculator. Begitu keduanya bisa berbeda, dewan juri kehilangan
 * jaminan bahwa pembatalannya langsung berlaku -- jaminan yang docblock
 * TandingScoreCalculator sengaja tolak tukar dengan kecepatan apa pun.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

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
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->kalkulator = new TandingScoreCalculator;
    $this->snapshot = new SnapshotSkor($this->kalkulator);

    $this->nilai = fn (Sudut $sudut, string $jenis, int $babak = 1) => ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => $babak, 'corner' => $sudut,
        'point_type' => $jenis, 'value' => config("scoring.tanding.nilai.{$jenis}"),
        'server_ts' => now(),
    ]);

    $this->hukuman = fn (Sudut $sudut, int $points, int $babak = 1) => Penalty::create([
        'match_id' => $this->match->id, 'round' => $babak, 'corner' => $sudut,
        'tier' => 'teguran', 'level' => 1, 'points' => $points, 'violation_level' => 'sedang',
    ]);

    /** Bentuk yang sama persis dengan yang dikembalikan snapshot, tapi dihitung segar. */
    $this->segar = function (): array {
        $match = $this->match->fresh();
        $rekap = $this->kalkulator->rekapSkor($match);

        return [
            'total' => $rekap['total'],
            'babak' => $rekap['babak'],
            'teknik' => $this->kalkulator->rekapTeknik($match),
        ];
    };
});

it('mengembalikan angka yang sama dengan hitungan segar', function () {
    ($this->nilai)(Sudut::Merah, 'pukulan');
    ($this->nilai)(Sudut::Merah, 'tendangan');
    ($this->nilai)(Sudut::Biru, 'jatuhan');
    ($this->hukuman)(Sudut::Biru, -2);

    expect($this->snapshot->baca($this->match->fresh()))->toBe(($this->segar)());
});

/*
 * Inti keamanannya. Kalau satu urutan saja meninggalkan snapshot basi, angka
 * di layar berhenti mengikuti kenyataan -- dan yang paling mungkin
 * memergokinya adalah pesilat yang kalah.
 */
it('tetap sama dengan hitungan segar setelah urutan panjang perubahan', function () {
    $jenis = ['pukulan', 'tendangan', 'jatuhan'];
    $sudut = [Sudut::Merah, Sudut::Biru];
    $terbit = [];

    for ($i = 0; $i < 30; $i++) {
        $babak = intdiv($i, 10) + 1;

        // Baca di sela-sela penulisan, supaya snapshot benar-benar sempat
        // panas sebelum perubahan berikutnya membatalkannya.
        $this->snapshot->baca($this->match->fresh());

        $terbit[] = ($this->nilai)($sudut[$i % 2], $jenis[$i % 3], $babak);

        if ($i % 7 === 0) {
            ($this->hukuman)($sudut[($i + 1) % 2], -1, $babak);
        }

        // Batalkan sebagian nilai yang sudah terbit, lalu batalkan
        // pembatalannya -- keduanya jalan yang benar-benar dipakai dewan juri.
        if ($i % 5 === 4) {
            $terbit[$i - 1]->forceFill(['voided_at' => now(), 'void_reason' => 'uji'])->save();
        }

        if ($i % 11 === 10) {
            $terbit[$i - 2]->forceFill(['voided_at' => null, 'void_reason' => null])->save();
        }

        expect($this->snapshot->baca($this->match->fresh()))->toBe(($this->segar)());
    }
});

it('membatalkan snapshot begitu satu nilai dibatalkan', function () {
    $nilai = ($this->nilai)(Sudut::Merah, 'jatuhan');

    $this->snapshot->baca($this->match->fresh());
    expect($this->match->fresh()->snapshot_pada)->not->toBeNull();

    $nilai->forceFill(['voided_at' => now(), 'void_reason' => 'uji'])->save();

    expect($this->match->fresh()->snapshot_pada)->toBeNull();
});

it('membatalkan snapshot begitu satu hukuman terbit', function () {
    ($this->nilai)(Sudut::Merah, 'pukulan');
    $this->snapshot->baca($this->match->fresh());

    ($this->hukuman)(Sudut::Merah, -1);

    expect($this->match->fresh()->snapshot_pada)->toBeNull();
});

/*
 * Alasan seluruh kelas ini ada: pembacaan berulang di antara dua perubahan
 * tidak boleh menyentuh score_events maupun penalties sama sekali.
 */
it('tidak menanyakan apa pun ke basis data saat snapshot masih sahih', function () {
    ($this->nilai)(Sudut::Merah, 'pukulan');
    ($this->nilai)(Sudut::Biru, 'tendangan');

    $match = $this->match->fresh();
    $this->snapshot->baca($match);

    $kueri = [];
    DB::listen(function ($q) use (&$kueri) {
        $kueri[] = $q->sql;
    });

    $this->snapshot->baca($match);

    expect($kueri)->toBe([]);
});

it('menghitung ulang setelah snapshot dibatalkan', function () {
    ($this->nilai)(Sudut::Merah, 'pukulan');

    $sebelum = $this->snapshot->baca($this->match->fresh());
    expect($sebelum['total']['merah'])->toBe(1);

    ($this->nilai)(Sudut::Merah, 'jatuhan');

    $sesudah = $this->snapshot->baca($this->match->fresh());
    expect($sesudah['total']['merah'])->toBe(4)
        ->and($sesudah)->toBe(($this->segar)());
});

it('menyusun ulang seluruh partai lewat perintah bangun-ulang', function () {
    ($this->nilai)(Sudut::Merah, 'tendangan');

    // Snapshot yang sengaja dibuat salah, seperti hasil suntingan langsung di
    // basis data yang tidak lewat model.
    DB::table('matches')->where('id', $this->match->id)->update([
        'snapshot_skor' => json_encode(['total' => ['merah' => 99, 'biru' => 99], 'babak' => [], 'teknik' => []]),
        'snapshot_pada' => now(),
    ]);

    expect($this->snapshot->baca($this->match->fresh())['total']['merah'])->toBe(99);

    $this->artisan('silat:snapshot-skor', ['--bangun-ulang' => true])->assertSuccessful();

    expect($this->snapshot->baca($this->match->fresh()))->toBe(($this->segar)());
});
