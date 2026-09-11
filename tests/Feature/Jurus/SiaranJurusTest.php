<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Broadcasting\JurusPenampilanChannelAuthorizer;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Events\Jurus\PenampilanJurusBerubah;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusBattle;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\SusunBaganJurus;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\Event;

/*
 * Jurus disiarkan, sama seperti Tanding.
 *
 * Sebelum berkas ini, satu-satunya modul yang tidak punya siaran adalah Jurus:
 * juri mengirim nilai dan panel operator tetap menulis "Belum ada juri yang
 * mengirim nilai" sampai ada yang menekan muat ulang -- terukur di uji
 * lapangan. Panel Tanding memperbarui dirinya dalam ~1 detik; panel Jurus
 * tidak pernah, sampai halamannya dimuat ulang dengan tangan.
 *
 * Yang diuji di sini bukan isi muatannya melainkan bahwa tiap perubahan yang
 * terlihat di layar MENERBITKAN siaran, dan bahwa channel-nya hanya bisa
 * dimasuki petugas yang memang berhak melihat penilaian.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->daftar = function () use ($kontingen) {
        $registrasi = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
        $registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $registrasi;
    };

    $this->performance = JurusPerformance::create([
        'jurus_event_id' => $this->event->id,
        'registration_id' => ($this->daftar)()->id,
        'tahap' => 'final',
    ]);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->operator = ($this->buatUser)('operator-it');
    $this->pengawas = ($this->buatUser)('pengawas-wasit-juri');
    $this->juri = ($this->buatUser)('juri');
    $this->ketua = ($this->buatUser)('ketua-pertandingan');
});

it('menyiarkan saat juri mengirim nilai', function () {
    Event::fake([PenampilanJurusBerubah::class]);

    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]), ['value' => 9.55])
        ->assertOk();

    Event::assertDispatched(
        PenampilanJurusBerubah::class,
        fn (PenampilanJurusBerubah $e) => $e->performanceId === $this->performance->id && $e->sebab === 'nilai',
    );
});

it('menyiarkan saat timer penampilan mulai dan berhenti', function () {
    Event::fake([PenampilanJurusBerubah::class]);

    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.mulai', [$this->tournament, $this->performance]))
        ->assertOk();

    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.berhenti', [$this->tournament, $this->performance]))
        ->assertOk();

    Event::assertDispatchedTimes(PenampilanJurusBerubah::class, 2);
});

it('menyiarkan saat pengawas menjatuhkan pengurangan', function () {
    Event::fake([PenampilanJurusBerubah::class]);

    $this->actingAs($this->pengawas)
        ->postJson(route('admin.turnamen.jurus.penampilan.pengurangan-pengawas', [$this->tournament, $this->performance]), [
            'alasan' => 'keluar_gelanggang',
        ])
        ->assertOk();

    Event::assertDispatched(
        PenampilanJurusBerubah::class,
        fn (PenampilanJurusBerubah $e) => $e->sebab === 'pengurangan',
    );
});

it('menyiarkan saat penampilan disahkan', function () {
    // Pengesahan menuntut penampilan yang sudah SELESAI, bukan sekadar sudah
    // dinilai -- jadi timernya dijalankan dan dihentikan lebih dulu, persis
    // seperti di gelanggang.
    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.mulai', [$this->tournament, $this->performance]))
        ->assertOk();

    $this->actingAs($this->operator)
        ->postJson(route('admin.turnamen.jurus.penampilan.timer.berhenti', [$this->tournament, $this->performance]))
        ->assertOk();

    foreach (range(1, 4) as $nomor) {
        $juri = ($this->buatUser)('juri');

        $this->actingAs($juri)
            ->postJson(route('admin.turnamen.jurus.penampilan.nilai', [$this->tournament, $this->performance]), ['value' => 9.50])
            ->assertOk();
    }

    Event::fake([PenampilanJurusBerubah::class]);

    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.jurus.penampilan.sahkan', [$this->tournament, $this->performance]))
        ->assertOk();

    Event::assertDispatched(
        PenampilanJurusBerubah::class,
        fn (PenampilanJurusBerubah $e) => $e->sebab === 'sahkan',
    );
});

it('menyiarkan saat penampilan didiskualifikasi', function () {
    Event::fake([PenampilanJurusBerubah::class]);

    $this->actingAs($this->pengawas)
        ->postJson(route('admin.turnamen.jurus.penampilan.diskualifikasi', [$this->tournament, $this->performance]))
        ->assertOk();

    Event::assertDispatched(
        PenampilanJurusBerubah::class,
        fn (PenampilanJurusBerubah $e) => $e->sebab === 'diskualifikasi',
    );
});

/*
 * Penampilan yang berdiri di dalam battle menyiarkan ke DUA channel: channel
 * penampilannya sendiri, dan channel battle-nya. Halaman perbandingan membaca
 * dua penampilan sekaligus, dan yang menekan "Tetapkan pemenang" di situ
 * membandingkan dua angka -- angka yang, tanpa channel kedua ini, berhenti
 * bergerak sejak halaman dibuka.
 */
it('menyiarkan ke channel penampilan dan channel battle sekaligus', function () {
    $this->event->update(['format' => FormatJurus::Battle]);

    ($this->daftar)();
    ($this->daftar)();

    $bagan = (new SusunBaganJurus)->untukNomor($this->event->fresh(), acak: false);
    $battle = JurusBattle::where('jurus_bracket_id', $bagan->id)->firstOrFail();

    $this->performance->update(['jurus_battle_id' => $battle->id, 'sudut' => 'biru']);

    $saluran = collect((new PenampilanJurusBerubah($this->performance->fresh(), 'nilai'))->broadcastOn())
        ->map(fn ($c) => (string) $c)
        ->all();

    expect($saluran)->toContain('private-jurus.penampilan.'.$this->performance->id)
        ->and($saluran)->toContain('private-jurus.battle.'.$battle->id);
});

it('menamai siaran dengan nama yang didengarkan panel', function () {
    expect((new PenampilanJurusBerubah($this->performance, 'nilai'))->broadcastAs())->toBe('jurus.penampilan');
});

/*
 * Panel yang mengikuti GELANGGANG -- papan, kendali, ketua -- tidak tahu id
 * penampilan yang sedang tayang sampai mereka menariknya. Tanpa channel
 * gelanggang, nilai yang masuk tidak menggerakkan satu layar pun sampai
 * seseorang memuat ulang.
 */
it('menyiarkan ke channel presence gelanggang penampilannya', function () {
    $arena = \App\Models\Arena::factory()->for($this->tournament)->create();
    $this->performance->update(['arena_id' => $arena->id]);

    $saluran = collect((new PenampilanJurusBerubah($this->performance->fresh(), 'nilai'))->broadcastOn())
        ->map(fn ($c) => (string) $c)
        ->all();

    expect($saluran)->toContain('presence-arena.'.$arena->id);
});

it('tidak menyiarkan Jurus ke channel publik meski saklar overlay menyala', function () {
    config(['overlay.enabled' => true, 'live.enabled' => true]);

    $arena = \App\Models\Arena::factory()->for($this->tournament)->create();
    $this->performance->update(['arena_id' => $arena->id]);

    $saluran = collect((new PenampilanJurusBerubah($this->performance->fresh(), 'nilai'))->broadcastOn())
        ->map(fn ($c) => (string) $c)
        ->all();

    expect($saluran)->not->toContain('public-live.'.$arena->id);
});

it('tidak menambah channel gelanggang untuk penampilan yang belum dijadwalkan', function () {
    $saluran = collect((new PenampilanJurusBerubah($this->performance, 'nilai'))->broadcastOn())
        ->map(fn ($c) => (string) $c)
        ->all();

    expect($saluran)->toBe(['private-jurus.penampilan.'.$this->performance->id]);
});

/** Kelima event Tanding tetap membuka channel publiknya saat saklarnya menyala. */
it('menjaga siaran publik Tanding tetap terbuka lewat SaluranArena::untuk', function () {
    config(['overlay.enabled' => true]);

    $saluran = collect(\App\Support\Live\SaluranArena::untuk(7))->map(fn ($c) => (string) $c)->all();

    expect($saluran)->toBe(['presence-arena.7', 'public-live.7']);
});

it('hanya mengizinkan petugas penilaian masuk channel penampilan', function () {
    $otorisasi = app(JurusPenampilanChannelAuthorizer::class);

    expect($otorisasi->join($this->juri, $this->performance->id))->toBeTruthy()
        ->and($otorisasi->join($this->operator, $this->performance->id))->toBeTruthy()
        ->and($otorisasi->join(($this->buatUser)('official-kontingen'), $this->performance->id))->toBeFalse();
});
