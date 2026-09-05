<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\StatusBabak;
use App\Enums\Sudut;
use App\Enums\TingkatPelanggaran;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchRoundReopen;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\BabakSusulan;
use App\Support\Scoring\CatatInputJuri;
use App\Support\Scoring\ConsensusEvaluator;
use App\Support\Scoring\MatchTimer;
use App\Support\Scoring\TandingScoreCalculator;
use App\Support\Scoring\TanggaHukuman;

/*
 * Nilai atau hukuman yang terlewat di babak sebelumnya sampai sekarang tidak
 * bisa dicatat lagi. Yang dibuat BUKAN cara menurunkan current_round -- itu
 * membuat skor babak berikutnya menggantung tanpa babak yang memilikinya.
 * Satu babak lama dibuka, babak berjalan dijeda, dan current_round tidak
 * pernah bergerak mundur.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);
    $arena = Arena::factory()->for($this->tournament)->create();

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $arena->id, 'order_in_arena' => 1,
    ]);

    $this->pengendali = User::factory()->create();
    $this->timer = new MatchTimer;
    $this->susulan = new BabakSusulan($this->timer);

    /** Menjalankan babak 1 sampai selesai, lalu memulai babak 2. */
    $this->majuKeBabakDua = function () {
        $this->timer->mulaiBabak($this->match, 1);
        $this->timer->selesaikanBabak($this->match->babakAktif());
        $this->timer->mulaiBabak($this->match->refresh(), 2);

        return $this->match->refresh();
    };
});

it('membuka babak lama tanpa menurunkan current_round', function () {
    ($this->majuKeBabakDua)();

    $this->susulan->buka($this->match, 1, $this->pengendali);

    expect($this->match->fresh())
        ->susulan_round->toBe(1)
        ->current_round->toBe(2)
        ->susulan_dibuka_oleh->toBe($this->pengendali->id);
});

it('menjeda babak berjalan saat susulan dibuka', function () {
    ($this->majuKeBabakDua)();

    $this->susulan->buka($this->match, 1, $this->pengendali);

    expect($this->match->fresh()->babakAktif()->status)->toBe(StatusBabak::Jeda)
        ->and($this->match->fresh()->susulan_jeda_otomatis)->toBeTrue();
});

it('melanjutkan babak berjalan saat susulan ditutup', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    $this->susulan->tutup($this->match->fresh(), $this->pengendali);

    expect($this->match->fresh()->susulan_round)->toBeNull()
        ->and($this->match->fresh()->babakAktif()->berjalan())->toBeTrue();
});

/*
 * Babak yang sudah dijeda pengendali sebelumnya -- karena cedera, karena
 * protes -- tidak boleh ikut berjalan lagi hanya karena susulan selesai
 * dicatat. Babak yang tiba-tiba jalan tanpa ada yang menekan adalah kejutan
 * yang mahal di gelanggang.
 */
it('membiarkan babak yang sudah dijeda lebih dulu tetap jeda', function () {
    ($this->majuKeBabakDua)();
    $this->timer->jeda($this->match->fresh()->babakAktif());

    $this->susulan->buka($this->match->fresh(), 1, $this->pengendali);
    $this->susulan->tutup($this->match->fresh(), $this->pengendali);

    expect($this->match->fresh()->babakAktif()->status)->toBe(StatusBabak::Jeda);
});

it('menolak membuka babak yang belum lewat', function () {
    ($this->majuKeBabakDua)();

    expect(fn () => $this->susulan->buka($this->match, 2, $this->pengendali))
        ->toThrow(RuntimeException::class, 'Hanya babak yang sudah lewat');
});

it('menolak membuka babak yang belum pernah diselesaikan', function () {
    $this->timer->mulaiBabak($this->match, 1);
    $this->match->update(['current_round' => 2]);

    expect(fn () => $this->susulan->buka($this->match->fresh(), 1, $this->pengendali))
        ->toThrow(RuntimeException::class, 'belum pernah diselesaikan');
});

it('menolak membuka babak setelah hasil disahkan', function () {
    ($this->majuKeBabakDua)();
    $this->match->update(['ratified_at' => now(), 'ratified_by' => $this->pengendali->id]);

    expect(fn () => $this->susulan->buka($this->match->fresh(), 1, $this->pengendali))
        ->toThrow(RuntimeException::class, 'sudah disahkan');
});

it('menolak membuka dua babak sekaligus', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    expect(fn () => $this->susulan->buka($this->match->fresh(), 1, $this->pengendali))
        ->toThrow(RuntimeException::class, 'sedang dibuka');
});

/*
 * Membuka kembali babak yang sudah ditutup adalah hal yang paling mungkin
 * digugat sesudah kejuaraan usai. Jawabannya tidak boleh berupa ingatan
 * siapa pun.
 */
it('meninggalkan jejak permanen siapa membuka dan menutup', function () {
    ($this->majuKeBabakDua)();
    $penutup = User::factory()->create();

    $this->susulan->buka($this->match, 1, $this->pengendali);
    $this->susulan->tutup($this->match->fresh(), $penutup);

    $jejak = MatchRoundReopen::where('match_id', $this->match->id)->sole();

    expect($jejak)
        ->round->toBe(1)
        ->opened_by->toBe($this->pengendali->id)
        ->closed_by->toBe($penutup->id)
        ->and($jejak->opened_at)->not->toBeNull()
        ->and($jejak->closed_at)->not->toBeNull();
});

it('menerima nilai juri untuk babak yang sedang dibuka', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    $catat = new CatatInputJuri(new ConsensusEvaluator);
    $juri = User::factory()->create();

    $input = $catat($this->match->fresh(), $juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->rejected_reason)->toBeNull()
        ->and($input->round)->toBe(1);
});

/*
 * Selama satu babak dibuka untuk susulan, HANYA babak itu yang menerima input
 * -- termasuk menolak babak berjalan. Kalau tidak, juri menekan nilai ke babak
 * 2 sementara wasit di sebelahnya menghukum babak 1.
 */
it('menolak nilai untuk babak berjalan selagi susulan terbuka', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    $catat = new CatatInputJuri(new ConsensusEvaluator);
    $juri = User::factory()->create();

    $input = $catat($this->match->fresh(), $juri, 2, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->rejected_reason)->toContain('dibuka untuk input susulan');
});

it('kembali menolak babak lama setelah susulan ditutup', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);
    $this->susulan->tutup($this->match->fresh(), $this->pengendali);

    $catat = new CatatInputJuri(new ConsensusEvaluator);
    $juri = User::factory()->create();

    $input = $catat($this->match->fresh(), $juri, 1, Sudut::Merah, JenisSerangan::Pukulan);

    expect($input->rejected_reason)->toBe('Babak ini bukan babak yang sedang berjalan.');
});

/*
 * Timer babak susulan TIDAK dijalankan, dan itu bukan kelalaian: yang dicatat
 * adalah kejadian menit-menit lalu. Menyalakan jamnya berarti mengarang durasi
 * yang tidak pernah ada, dan durasi itu ikut masuk berita acara.
 */
it('tidak menjalankan timer babak yang dibuka', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    expect($this->match->fresh()->babakSusulan()->status)->toBe(StatusBabak::Selesai);
});

it('mencatat skor susulan ke babak yang benar', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    $tangga = new TanggaHukuman($this->timer);
    $tangga->catat($this->match->fresh(), Sudut::Merah, 1, TingkatPelanggaran::Sedang, null, $this->pengendali);

    $rekap = (new TandingScoreCalculator)->rekapSkor($this->match->fresh());

    expect($rekap['babak'][1]['merah'])->toBe(-1);
});

/*
 * Ditemukan lewat pengujian browser: tombol "Lanjutkan" tetap aktif selagi
 * susulan terbuka, dan menekannya menjalankan kembali jam babak berjalan.
 *
 * Akibatnya menit-menit pertandingan habis sementara SELURUH input dikunci ke
 * babak susulan -- dan waktu itu tidak bisa dikembalikan.
 */
it('menolak melanjutkan babak berjalan selagi susulan terbuka', function () {
    ($this->majuKeBabakDua)();
    $this->susulan->buka($this->match, 1, $this->pengendali);

    $aktif = $this->match->fresh()->babakAktif();

    expect(fn () => $this->timer->lanjutkan($aktif))
        ->toThrow(RuntimeException::class, 'sedang dibuka untuk input susulan');
});

it('menolak memulai babak baru selagi susulan terbuka', function () {
    ($this->majuKeBabakDua)();
    $this->timer->selesaikanBabak($this->match->fresh()->babakAktif());
    $this->susulan->buka($this->match->fresh(), 1, $this->pengendali);

    expect(fn () => $this->timer->mulaiBabak($this->match->fresh(), 3))
        ->toThrow(RuntimeException::class, 'sedang dibuka untuk input susulan');
});
