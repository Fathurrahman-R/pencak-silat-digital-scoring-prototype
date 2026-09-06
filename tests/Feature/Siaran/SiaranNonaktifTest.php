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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Perilaku kedua saklar siaran saat dimatikan.
 *
 * Yang dikunci di sini bukan cuma "halamannya berubah", melainkan tiga
 * janji yang mudah hanyut: rutenya tetap terdaftar, halamannya tidak
 * memuat bundel JavaScript gelanggang, dan endpoint state-nya membalas
 * tanpa menyentuh database sama sekali.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $this->kelas->id, 'size' => 2]);

    $peserta = collect([1, 2])->map(function () use ($kontingen) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $this->kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $reg;
    });

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $peserta[0]->id,
        'blue_registration_id' => $peserta[1]->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
        'arena_id' => $this->arena->id,
    ]);
});

it('merender halaman siaran nonaktif, bukan 404, saat overlay dimatikan', function (string $rute) {
    config(['overlay.enabled' => false]);

    // Parameter disusun di dalam uji, bukan lewat dataset: dataset Pest
    // dinilai di luar konteks uji, jadi $this->arena belum ada di sana.
    $parameter = $rute === 'overlay.athlete'
        ? ['arena' => $this->arena, 'corner' => 'merah']
        : ['arena' => $this->arena];

    $this->get(route($rute, $parameter))
        ->assertOk()
        ->assertSee('Overlay siaran dimatikan')
        ->assertSee('OVERLAY_ENABLED=true');
})->with([
    'overlay.scorebug',
    'overlay.athlete',
    'overlay.breakdown',
    'overlay.result',
]);

it('merender halaman live score nonaktif saat live score dimatikan', function () {
    config(['live.enabled' => false]);

    $this->get(route('live.gelanggang', $this->arena))
        ->assertOk()
        ->assertSee('Live score sedang tidak ditayangkan')
        // Nama kunci .env tidak disiarkan ke penonton: halaman ini bisa
        // diteruskan tunnel ke internet, beda dari overlay yang LAN saja.
        ->assertDontSee('LIVE_SCORE_ENABLED');
});

it('tidak memuat bundel JavaScript gelanggang di halaman nonaktif', function () {
    config(['overlay.enabled' => false, 'live.enabled' => false]);

    foreach ([route('overlay.scorebug', $this->arena), route('live.gelanggang', $this->arena)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('overlayLive', false)
            ->assertDontSee('silat.js', false);
    }
});

it('menjawab 503 di endpoint state overlay tanpa menyentuh database', function () {
    config(['overlay.enabled' => false]);

    $query = 0;
    DB::listen(function () use (&$query) {
        $query++;
    });

    $this->getJson(route('overlay.state', $this->arena))
        ->assertStatus(503)
        ->assertExactJson(['aktif' => false, 'pesan' => 'Siaran sedang dimatikan di server ini.']);

    expect($query)->toBe(0);
});

it('menjawab 503 di endpoint state live score tanpa menyentuh database', function () {
    config(['live.enabled' => false]);

    $query = 0;
    DB::listen(function () use (&$query) {
        $query++;
    });

    $this->getJson(route('live.gelanggang.state', $this->arena))->assertStatus(503);

    expect($query)->toBe(0);
});

it('membiarkan halaman statis tetap hidup saat kedua siaran mati', function (string $rute) {
    config(['overlay.enabled' => false, 'live.enabled' => false]);

    $parameter = $rute === 'live.turnamen.bagan'
        ? ['tournament' => $this->tournament, 'weightClass' => $this->kelas]
        : ['tournament' => $this->tournament];

    $this->get(route($rute, $parameter))->assertOk();
})->with([
    'overlay.bracket',
    'live.turnamen',
    'live.turnamen.medali',
    'live.turnamen.bagan',
]);

it('mematikan overlay tanpa ikut mematikan live score', function () {
    config(['overlay.enabled' => false, 'live.enabled' => true]);

    $this->get(route('overlay.scorebug', $this->arena))->assertSee('Overlay siaran dimatikan');
    $this->get(route('live.gelanggang', $this->arena))->assertOk()->assertSee('overlayLive', false);
});

it('mematikan live score tanpa ikut mematikan overlay', function () {
    config(['overlay.enabled' => true, 'live.enabled' => false]);

    $this->get(route('live.gelanggang', $this->arena))->assertSee('Live score sedang tidak ditayangkan');
    $this->get(route('overlay.scorebug', $this->arena))->assertOk()->assertSee('overlayLive', false);
});

it('tetap mendaftarkan nama rute supaya tautan admin dan beranda tidak pecah', function () {
    config(['overlay.enabled' => false, 'live.enabled' => false]);

    foreach ([
        'overlay.scorebug', 'overlay.athlete', 'overlay.breakdown', 'overlay.result',
        'overlay.bracket', 'overlay.state',
        'live.gelanggang', 'live.gelanggang.state',
        'live.turnamen', 'live.turnamen.medali', 'live.turnamen.bagan',
    ] as $nama) {
        expect(Route::has($nama))->toBeTrue("rute {$nama} hilang");
    }

    $this->get('/')->assertOk();
});

/**
 * Gerbang koneksi Reverb. Penandanya bekerja terbalik dari yang biasa:
 * halaman menyatakan bahwa ia TIDAK butuh realtime, bukan bahwa ia butuh.
 * Halaman statis yang lupa menyatakannya hanya boros satu koneksi
 * menganggur; panel juri yang lupa menyatakannya akan diam-diam kehilangan
 * seluruh realtime-nya.
 */
it('tidak membuka koneksi realtime di halaman statis', function (string $rute) {
    $parameter = $rute === 'live.turnamen.bagan'
        ? ['tournament' => $this->tournament, 'weightClass' => $this->kelas]
        : ['tournament' => $this->tournament];

    $this->get(route($rute, $parameter))
        ->assertOk()
        ->assertSee('name="realtime" content="0"', false);
})->with([
    'overlay.bracket',
    'live.turnamen',
    'live.turnamen.medali',
    'live.turnamen.bagan',
]);

it('tidak membuka koneksi realtime di beranda', function () {
    $this->get('/')->assertOk()->assertSee('name="realtime" content="0"', false);
});

it('tetap membuka koneksi realtime di halaman gelanggang saat siaran menyala', function (string $rute) {
    config(['overlay.enabled' => true, 'live.enabled' => true]);

    $this->get(route($rute, ['arena' => $this->arena]))
        ->assertOk()
        ->assertDontSee('name="realtime" content="0"', false);
})->with([
    'overlay.scorebug',
    'overlay.breakdown',
    'overlay.result',
    'live.gelanggang',
]);
