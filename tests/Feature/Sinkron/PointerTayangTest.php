<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\KesiapanHulu;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Scoring\MatchTimer;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PenerapPaket;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Apa yang sedang ditayangkan gelanggang adalah catatan LOKAL, dan berkas ini
 * yang menjaganya tetap begitu.
 *
 * Pointer itu sempat tinggal sebagai kolom di `arenas` -- tabel bergolongan
 * GLOBAL, yang menurut aturan satu penulis hanya boleh ditulis node global.
 * Penerapan paket meng-upsert seluruh kolom baris yang diterimanya, jadi
 * begitu node global menyunting satu gelanggang apa pun -- ganti nama,
 * nonaktifkan, tambah keterangan -- penarikan berikutnya membawa nilai
 * pointer versi global, yang selalu kosong, dan gelanggang yang sedang
 * bertanding kehilangan partai aktifnya.
 *
 * Kegagalannya diam: tidak ada galat, tidak ada baris log. Yang terlihat cuma
 * seluruh panel gelanggang serentak kembali ke layar "menunggu pengendali
 * memilih partai", di tengah babak.
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.token' => 'rahasia-uji',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create([
        'name' => 'Gelanggang A',
        'code' => 'A',
    ]);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bagan = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $this->match = SilatMatch::create([
        'bracket_id' => $bagan->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
    ]);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($this->pengendali->id);

    (new PointerTayang(new MatchTimer, app(KesiapanHulu::class)))
        ->tunjuk($this->arena, $this->match, $this->pengendali);
});

/*
 * Inti berkas ini. Node global mengirimkan barisnya sendiri -- versi yang
 * benar menurut dia, dan yang memang harus diterima gelanggang: nama
 * gelanggang berubah. Yang TIDAK boleh ikut berubah adalah apa yang sedang
 * ditayangkan, karena itu bukan pengetahuan node global.
 */
it('mempertahankan partai aktif saat node global menyunting gelanggangnya', function () {
    $sebelum = $this->arena->fresh()->active_match_id;

    expect($sebelum)->toBe($this->match->id);

    /*
     * Bentuk baris `arenas` seperti yang dikirim NODE GLOBAL: seluruh kolom
     * tabelnya, dengan nama yang baru disunting panitia di sana.
     *
     * Kolom pointer sengaja dikosongkan, dan itu bukan karangan uji ini
     * melainkan keadaan node global yang sebenarnya: ia tidak melayani
     * gelanggang mana pun, jadi tidak pernah menunjuk partai. Menyalin baris
     * lokal apa adanya justru menutupi cacatnya -- pointer yang sama ditulis
     * kembali, dan uji lolos tanpa pernah menyentuh persoalannya.
     */
    $dariGlobal = (array) DB::table('arenas')->where('id', $this->arena->id)->first();
    $dariGlobal['name'] = 'Gelanggang A (Utama)';

    foreach (['active_match_id', 'active_match_set_at', 'active_match_set_by'] as $kolomPointer) {
        if (array_key_exists($kolomPointer, $dariGlobal)) {
            $dariGlobal[$kolomPointer] = null;
        }
    }

    app(PenerapPaket::class)->terapkan([
        'baris' => [[
            'tabel' => 'arenas',
            'id' => (string) $this->arena->id,
            'aksi' => CatatanKeluar::SIMPAN,
            'data' => $dariGlobal,
        ]],
    ]);

    $sesudah = $this->arena->fresh();

    // Suntingan node global memang diterima -- itu haknya.
    expect($sesudah->name)->toBe('Gelanggang A (Utama)')
        // Tapi pointer tayang tidak ikut, karena ia bukan miliknya.
        ->and($sesudah->active_match_id)->toBe($this->match->id);
});

/*
 * Kebalikannya juga harus berlaku: pointer adalah catatan lokal gelanggang,
 * jadi ia TIDAK ikut terkirim keluar sebagai milik node global. Node yang
 * memegang gelanggang inilah pemiliknya.
 */
it('mengakui pointer tayang sebagai milik gelanggang yang memegangnya', function () {
    $baris = (array) DB::table('arena_tayang')->where('arena_id', $this->arena->id)->first();

    expect(app(Kepemilikan::class)->milikNodeIni('arena_tayang', $baris))
        ->toBeTrue();

    // Node yang memegang gelanggang LAIN tidak boleh mengklaimnya.
    config(['sinkron.arena' => 'B']);

    expect(app(Kepemilikan::class)->milikNodeIni('arena_tayang', $baris))
        ->toBeFalse();
});

/*
 * Pointer tidak lagi berupa kolom di `arenas`. Kalau suatu saat ia kembali ke
 * sana, seluruh sebab di kepala berkas ini hidup lagi tanpa ada yang sadar --
 * uji ini yang menyalakan lampunya.
 */
it('tidak menyimpan pointer sebagai kolom di tabel arenas', function () {
    $kolom = array_map('strtolower', Schema::getColumnListing('arenas'));

    expect($kolom)->not->toContain('active_match_id')
        ->and($kolom)->not->toContain('active_match_set_at')
        ->and($kolom)->not->toContain('active_match_set_by');
});
