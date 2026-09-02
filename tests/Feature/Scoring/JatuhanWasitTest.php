<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JudgeVerification;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/**
 * Nilai mutlak jatuhan diterbitkan wasit, bukan juri.
 *
 * Jatuhan sederajat dengan hukuman: nilainya mutlak, dan yang memutuskan
 * adalah orang yang berdiri di gelanggang dan melihat jatuhnya -- bukan tiga
 * juri yang dikonsensuskan. Karena itu tombolnya lepas dari panel juri, dan
 * penolakannya berdiri di server: panel juri berjalan di ponsel yang tetap
 * memegang halaman lamanya setelah aplikasi diperbarui.
 */
beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };

    $this->operator = $buatUser('operator-it');
    $this->dewan = $buatUser('pengawas-wasit-juri');
    $this->juri = $buatUser('juri');
    $this->wasit = $buatUser('wasit');

    $gelanggang = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $gelanggang->operators()->attach($this->operator);
    $this->match->update(['arena_id' => $gelanggang->id, 'order_in_arena' => 1]);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->wasit->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->mulaiBabak = function () {
        $this->actingAs($this->operator)->post(
            route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]),
            ['babak' => 1],
        );
    };
});

it('menerbitkan nilai mutlak jatuhan dari panel wasit', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasit)
        ->postJson(route('admin.turnamen.partai.jatuhan', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'red',
        ])
        ->assertOk();

    $nilai = ScoreEvent::where('match_id', $this->match->id)->firstOrFail();

    expect($nilai->corner)->toBe(Sudut::Merah)
        ->and($nilai->point_type)->toBe(JenisSerangan::Jatuhan)
        ->and($nilai->value)->toBe(3)
        // Penerbitnya tercatat: tanpa itu, riwayat menampilkan +3 yang seolah
        // muncul tanpa satu pun penekan.
        ->and($nilai->issued_by)->toBe($this->wasit->id)
        ->and($nilai->mutlak())->toBeTrue()
        // Tidak ada tekanan juri yang menyusunnya.
        ->and($nilai->judgeInputs()->count())->toBe(0);
});

it('menolak jatuhan yang dikirim juri lewat jalur nilai biasa', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'red',
            'jenis' => 'jatuhan',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('jenis');

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

it('tetap menerima pukulan dan tendangan dari juri', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'red',
            'jenis' => 'tendangan',
        ])
        ->assertOk();
});

it('menolak penerbitan jatuhan oleh juri', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juri)
        ->postJson(route('admin.turnamen.partai.jatuhan', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'blue',
        ])
        ->assertForbidden();

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});

/*
 * Verifikasi jatuhan berhenti sebagai masukan. Menautkannya ke nilai yang
 * terbit membuat berita acara bisa menunjukkan bahwa wasit memutuskan setelah
 * menimbang jawaban juri, bukan sendirian.
 */
it('menautkan verifikasi jatuhan ke nilai yang diterbitkan wasit', function () {
    ($this->mulaiBabak)();

    $verifikasi = JudgeVerification::create([
        'match_id' => $this->match->id,
        'round' => 1,
        'jenis' => 'jatuhan',
        'status' => JudgeVerification::SELESAI,
        'diminta_oleh' => $this->wasit->id,
        'diminta_at' => now(),
        'hasil' => 'red',
    ]);

    $this->actingAs($this->wasit)
        ->postJson(route('admin.turnamen.partai.jatuhan', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'red',
            'verifikasi_id' => $verifikasi->id,
        ])
        ->assertOk();

    $nilai = ScoreEvent::where('match_id', $this->match->id)->firstOrFail();

    expect($verifikasi->fresh()->score_event_id)->toBe($nilai->id);
});

it('menyebut Wasit sebagai penerbit di riwayat panel', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasit)->postJson(
        route('admin.turnamen.partai.jatuhan', [$this->tournament, $this->match]),
        ['babak' => 1, 'corner' => 'blue'],
    );

    $riwayat = $this->actingAs($this->dewan)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json('riwayat');

    expect(collect($riwayat)->firstWhere('tipe', 'nilai')['oleh'])->toBe('Wasit');
});

/*
 * Wewenangnya melekat pada penugasan, bukan pada peran. Dua gelanggang
 * berjalan bersamaan, dan wasit gelanggang sebelah tidak menerbitkan nilai di
 * partai ini.
 */
it('menolak jatuhan dari wasit yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $wasitLain = User::factory()->create();
    $wasitLain->syncRoles(['wasit']);

    $this->actingAs($wasitLain)
        ->postJson(route('admin.turnamen.partai.jatuhan', [$this->tournament, $this->match]), [
            'babak' => 1,
            'corner' => 'red',
        ])
        ->assertForbidden();

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(0);
});
