<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Events\Gelanggang\PartaiAktifBerubah;
use App\Models\Arena;
use App\Models\ArenaTayang;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\KesiapanHulu;
use App\Support\Bagan\PenjadwalPartai;
use App\Support\Gelanggang\PenolakanDapatDipaksa;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Live\StatePartaiPublik;
use App\Support\Scoring\MatchTimer;
use Illuminate\Support\Facades\Event;

/*
 * Sampai sekarang "partai apa yang sedang ditayangkan gelanggang ini" tidak
 * pernah dinyatakan di basis data -- ia diturunkan dari matches.status. Berkas
 * ini menguji pointer yang menggantikannya, DAN menguji bahwa turunan lamanya
 * tetap bekerja untuk gelanggang yang pointernya belum terisi.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create();
    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $this->pengendali = User::factory()->create();
    $this->pointer = new PointerTayang(new MatchTimer, app(KesiapanHulu::class));

    $this->buatPartai = function (int $posisi, array $ganti = []) use ($kelas) {
        $daftar = fn () => tap(
            Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => $kelas->id]),
            fn ($r) => $r->athletes()->attach(Athlete::factory()->for($this->kontingen)->create()),
        );

        return SilatMatch::create(array_merge([
            'bracket_id' => $this->bracket->id, 'round' => 1, 'position' => $posisi,
            'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
            'arena_id' => $this->arena->id, 'order_in_arena' => $posisi,
        ], $ganti));
    };
});

it('menunjuk partai dan mencatat siapa yang menunjuknya', function () {
    Event::fake([PartaiAktifBerubah::class]);

    $partai = ($this->buatPartai)(1);

    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);

    expect($this->arena->fresh())
        ->active_match_id->toBe($partai->id)
        ->active_match_set_by->toBe($this->pengendali->id)
        ->active_match_set_at->not->toBeNull();

    Event::assertDispatched(PartaiAktifBerubah::class);
});

it('menolak partai yang dijadwalkan di gelanggang lain', function () {
    $lain = Arena::factory()->for($this->tournament)->create();
    $partai = ($this->buatPartai)(1, ['arena_id' => $lain->id]);

    expect(fn () => $this->pointer->tunjuk($this->arena, $partai, $this->pengendali))
        ->toThrow(RuntimeException::class, 'tidak dijadwalkan di gelanggang ini');
});

/*
 * Ini pengaman utamanya: pengendali tidak boleh diam-diam meninggalkan partai
 * yang masih berjalan, karena juri dan wasit ikut berpindah bersamanya.
 */
it('menolak pindah dari partai yang masih berlangsung', function () {
    $berjalan = ($this->buatPartai)(1, ['status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1]);
    $berikutnya = ($this->buatPartai)(2);

    $this->pointer->tunjuk($this->arena, $berjalan, $this->pengendali);

    expect(fn () => $this->pointer->tunjuk($this->arena->fresh(), $berikutnya, $this->pengendali))
        ->toThrow(RuntimeException::class, 'belum diakhiri');
});

/*
 * Pengendali yang salah memilih partai lalu terlanjur menekan "Mulai" harus
 * bisa keluar tanpa memaksa pemenang ditetapkan untuk partai yang belum
 * dimainkan. Karena itu paksa hanya MENJEDA babaknya, tidak mengakhiri partai.
 */
it('memindahkan paksa tanpa mengakhiri partai yang ditinggalkan', function () {
    $keliru = ($this->buatPartai)(1);
    $benar = ($this->buatPartai)(2);

    $this->pointer->tunjuk($this->arena, $keliru, $this->pengendali);
    (new MatchTimer)->mulaiBabak($keliru, 1);

    $this->pointer->tunjuk($this->arena->fresh(), $benar, $this->pengendali, paksa: true);

    expect($this->arena->fresh()->active_match_id)->toBe($benar->id)
        ->and($keliru->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG)
        ->and($keliru->fresh()->babakAktif()->berjalan())->toBeFalse();
});

it('mengosongkan gelanggang', function () {
    $partai = ($this->buatPartai)(1);
    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);

    $this->pointer->kosongkan($this->arena->fresh(), $this->pengendali);

    expect($this->arena->fresh()->active_match_id)->toBeNull();
});

it('menyusun antrean gelanggang urut tayang', function () {
    $ketiga = ($this->buatPartai)(3);
    $pertama = ($this->buatPartai)(1);
    $kedua = ($this->buatPartai)(2);

    expect($this->pointer->antrean($this->arena)->pluck('id')->all())
        ->toBe([$pertama->id, $kedua->id, $ketiga->id]);
});

it('menunjuk partai berikutnya yang belum selesai', function () {
    $pertama = ($this->buatPartai)(1, ['status' => SilatMatch::STATUS_SELESAI]);
    $kedua = ($this->buatPartai)(2);

    ArenaTayang::updateOrCreate(
        ['arena_id' => $this->arena->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $pertama->id, 'disetel_pada' => now()],
    );

    expect($this->pointer->berikutnya($this->arena->fresh())?->id)->toBe($kedua->id);
});

/*
 * Partai selesai tetap ditunjuk sampai pengendali memindahkannya. Tanpa ini
 * papan hasil siaran berkedip hilang beberapa detik setelah gong terakhir --
 * justru saat penonton paling ingin membacanya.
 */
it('mempertahankan partai selesai di pointer sampai dipindahkan', function () {
    $partai = ($this->buatPartai)(1);
    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);

    $partai->update(['status' => SilatMatch::STATUS_SELESAI]);

    expect($this->pointer->partaiAktif($this->arena->fresh())?->id)->toBe($partai->id);
});

/** Pointer basi tidak boleh menayangkan partai milik gelanggang lain. */
it('mengabaikan pointer yang partainya sudah lepas dari gelanggang', function () {
    $partai = ($this->buatPartai)(1);
    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);

    $partai->update(['arena_id' => null, 'order_in_arena' => null]);

    expect($this->pointer->partaiAktif($this->arena->fresh()))->toBeNull();
});

it('membaca pointer lebih dulu di state publik', function () {
    $berjalan = ($this->buatPartai)(1, ['status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1]);
    $ditunjuk = ($this->buatPartai)(2);

    ArenaTayang::updateOrCreate(
        ['arena_id' => $this->arena->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $ditunjuk->id, 'disetel_pada' => now()],
    );

    $state = app(StatePartaiPublik::class)($this->arena->fresh());

    expect($state['match']['id'])->toBe($ditunjuk->id)
        ->and($state['match']['id'])->not->toBe($berjalan->id);
});

/*
 * Jaring pengaman yang TIDAK boleh dibuang: gelanggang yang belum pernah
 * disentuh pengendali harus tetap menayangkan partai berjalan, kalau tidak
 * overlay siaran kosong di tengah kejuaraan tanpa penjelasan.
 */
it('jatuh ke turunan lama saat pointer belum terisi', function () {
    $berjalan = ($this->buatPartai)(1, ['status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1]);

    $state = app(StatePartaiPublik::class)($this->arena->fresh());

    expect($state['ada_partai'])->toBeTrue()
        ->and($state['match']['id'])->toBe($berjalan->id);
});

it('menolak melepas partai yang sedang ditayangkan gelanggang', function () {
    $partai = ($this->buatPartai)(1);
    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);

    expect(fn () => (new PenjadwalPartai)->lepas($partai->fresh()))
        ->toThrow(RuntimeException::class, 'sedang ditayangkan');
});

/*
 * Mengosongkan gelanggang tidak boleh jadi jalan buntu.
 *
 * Pesan penolakannya sendiri menawarkan "pindah paksa", tapi sampai sekarang
 * hanya tunjuk() yang menerima $paksa -- gelanggang yang partainya ditinggal
 * berjalan (perangkat pengendali mati, partai batal di tengah) tidak punya
 * satu jalan pun untuk dikosongkan.
 */
it('mengosongkan gelanggang paksa meski partai belum diakhiri', function () {
    $partai = ($this->buatPartai)(1);

    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);
    (new MatchTimer)->mulaiBabak($partai, 1);

    $this->pointer->kosongkan($this->arena->fresh(), $this->pengendali, paksa: true);

    expect($this->arena->fresh()->active_match_id)->toBeNull()
        ->and($partai->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak mengosongkan gelanggang tanpa paksa saat partai masih berjalan', function () {
    $partai = ($this->buatPartai)(1);

    $this->pointer->tunjuk($this->arena, $partai, $this->pengendali);
    (new MatchTimer)->mulaiBabak($partai, 1);

    expect(fn () => $this->pointer->kosongkan($this->arena->fresh(), $this->pengendali))
        ->toThrow(RuntimeException::class, 'belum diakhiri');
});

/*
 * Penolakan yang bisa ditembus paksa dibedakan JENISNYA, bukan cuma
 * kalimatnya. Panel harus bisa memutuskan apakah tombol "pindah paksa" pantas
 * ditawarkan tanpa mencocokkan teks pesan.
 */
it('menandai penolakan yang bisa ditembus paksa dengan jenis pengecualian sendiri', function () {
    $berjalan = ($this->buatPartai)(1);
    $berikutnya = ($this->buatPartai)(2);

    $this->pointer->tunjuk($this->arena, $berjalan, $this->pengendali);
    (new MatchTimer)->mulaiBabak($berjalan, 1);

    expect(fn () => $this->pointer->tunjuk($this->arena->fresh(), $berikutnya, $this->pengendali))
        ->toThrow(PenolakanDapatDipaksa::class);
});
