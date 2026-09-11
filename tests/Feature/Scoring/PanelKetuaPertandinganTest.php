<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JawabanVerifikasi;
use App\Enums\JenisKelamin;
use App\Enums\JenisVerifikasi;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\ManagerProtest;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\VarReview;
use App\Support\Scoring\PollingVerifikasi;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'arena_id' => $this->arena->id,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
    ]);

    $this->ketua = User::factory()->create();
    $this->ketua->syncRoles(['ketua-pertandingan']);

    $this->wasit = User::factory()->create();
    $this->wasit->syncRoles([peranSistem('wasit')]);
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->wasit->id, 'role' => MatchOfficial::ROLE_WASIT,
    ]);

    $this->juri = collect(range(1, 3))->map(function (int $nomor) {
        $user = User::factory()->create();
        $user->syncRoles(['juri']);
        MatchOfficial::create([
            'match_id' => $this->match->id, 'user_id' => $user->id,
            'role' => MatchOfficial::ROLE_JURI, 'number' => $nomor,
        ]);

        return $user;
    });

    $this->polling = app(PollingVerifikasi::class);
});

it('menampilkan panel Ketua Pertandingan', function () {
    $this->actingAs($this->ketua)
        ->get(route('admin.turnamen.ketua-pertandingan.index', $this->tournament))
        ->assertOk()
        ->assertSee('Ketua Pertandingan')
        // Tugas naskah yang belum punya padanan ditulis apa adanya, bukan
        // disembunyikan -- yang memegang jabatan berhak tahu apa yang tidak
        // bisa dilakukannya dari sini.
        ->assertSee('Pasal 13.4.d.7')
        ->assertSee('Pasal 13.4.d.6')
        ->assertSee('Pasal 13.4.d.5');
});

it('menolak pengguna tanpa wewenang mengelola partai', function () {
    $juri = $this->juri[0];

    $this->actingAs($juri)
        ->get(route('admin.turnamen.ketua-pertandingan.index', $this->tournament))
        ->assertForbidden();
});

it('membawa keadaan tiap gelanggang beserta skor dan aparatnya', function () {
    $state = $this->actingAs($this->ketua)
        ->getJson(route('admin.turnamen.ketua-pertandingan.state', $this->tournament))
        ->assertOk()
        ->json();

    $papan = collect($state['gelanggang'])->firstWhere('arena.id', $this->arena->id);

    expect($papan['tanding']['id'])->toBe($this->match->id)
        ->and($papan['tanding']['babak'])->toBe(1)
        ->and($papan['tanding']['aparat']['juri'])->toContain($this->juri[0]->name)
        ->and($papan['jurus'])->toBeNull();
});

it('memasukkan verifikasi yang berjalan ke antrean keputusan', function () {
    $verifikasi = $this->polling->minta($this->match, $this->ketua, 1, JenisVerifikasi::Jatuhan);
    $this->polling->jawab($verifikasi, $this->juri[0], JawabanVerifikasi::Merah);

    $antrean = $this->actingAs($this->ketua)
        ->getJson(route('admin.turnamen.ketua-pertandingan.state', $this->tournament))
        ->assertOk()
        ->json('antrean');

    $perkara = collect($antrean)->firstWhere('jenis', 'verifikasi');

    expect($perkara)->not->toBeNull()
        ->and($perkara['jumlah'])->toBe('1 / 3')
        ->and($perkara['partai_id'])->toBe($this->match->id);
});

it('menaikkan perkara yang tenggatnya sudah lewat ke atas antrean', function () {
    /*
     * Urutan antrean bukan kronologis melainkan menurut tenggat. Daftar
     * kronologis akan menaruh perkara yang baru masuk di atas perkara yang
     * sudah lewat batas waktunya -- padahal yang terakhir itu yang benar-benar
     * menghentikan gelanggang.
     */
    ManagerProtest::create([
        'match_id' => $this->match->id,
        'level' => ManagerProtest::TINGKAT_PERTAMA,
        'diajukan_at' => now()->subMinutes(200),
        'tenggat_formulir_at' => now()->subMinutes(190),
        'tenggat_keputusan_at' => now()->addMinutes(90),
        'formulir_dikembalikan_at' => now()->subMinutes(100),
    ]);

    $kartu = App\Models\ProtestCard::create([
        'match_id' => $this->match->id,
        'corner' => App\Enums\Sudut::Merah,
        'jumlah_dipakai' => 1,
    ]);

    VarReview::create([
        'match_id' => $this->match->id,
        'protest_card_id' => $kartu->id,
        'round' => 1,
        'corner' => App\Enums\Sudut::Merah,
        'kejadian' => 'Jatuhan tidak dihitung.',
        'diajukan_at' => now()->subMinutes(10),
        'tenggat_at' => now()->subMinutes(5),
    ]);

    $antrean = $this->actingAs($this->ketua)
        ->getJson(route('admin.turnamen.ketua-pertandingan.state', $this->tournament))
        ->assertOk()
        ->json('antrean');

    expect($antrean[0]['jenis'])->toBe('var-lewat-tenggat')
        ->and($antrean[0]['lewat'])->toBeTrue();
});

it('mengizinkan Ketua Pertandingan membuka verifikasi juri', function () {
    /*
     * Pasal 13 menyebut verifikasi datang dari Ketua Pertandingan MAUPUN
     * Wasit. Tanpa jalur ini, separuh naskahnya tidak terpenuhi.
     */
    $this->actingAs($this->ketua)
        ->postJson(route('admin.turnamen.partai.verifikasi.minta', [$this->tournament, $this->match]), [
            'babak' => 1,
            'jenis' => 'jatuhan',
        ])
        ->assertOk();

    expect(App\Models\JudgeVerification::where('match_id', $this->match->id)->berjalan()->count())->toBe(1);
});
