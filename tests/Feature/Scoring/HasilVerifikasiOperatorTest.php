<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JawabanVerifikasi;
use App\Enums\JenisKelamin;
use App\Enums\JenisVerifikasi;
use App\Enums\TingkatPelanggaran;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Panel\StatePartaiPanel;
use App\Support\Scoring\PollingVerifikasi;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Hasil verifikasi di panel operator.
 *
 * Sebelumnya ia tumbuh diam-diam di sudut blok verifikasi begitu ambang suara
 * tercapai -- sementara Wasit belum menekan apa pun. Dua hal salah dari situ:
 * ia terbaca sebagai keputusan yang sudah jadi, dan orang di sekitar meja
 * mengumumkannya mendahului Wasitnya sendiri; lalu ia tenggelam di antara enam
 * petak jawaban juri tepat pada detik ia benar-benar berarti.
 *
 * Sekarang: selama polling berjalan yang tampil cuma SUARA, dan hasilnya
 * mengambil layar sebagai modal begitu Wasit MENERAPKANNYA.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG,
        'current_round' => 1,
        'arena_id' => $this->arena->id,
        'order_in_arena' => 1,
    ]);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->wasit = ($this->buatUser)('wasit');
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $this->wasit->id, 'role' => MatchOfficial::ROLE_WASIT,
    ]);

    $this->juri = collect(range(1, 3))->map(function (int $nomor) {
        $user = ($this->buatUser)('juri');
        MatchOfficial::create([
            'match_id' => $this->match->id, 'user_id' => $user->id,
            'role' => MatchOfficial::ROLE_JURI, 'number' => $nomor,
        ]);

        return $user;
    });

    $this->pengendali = ($this->buatUser)('pengendali-gelanggang');
    $this->arena->pengendali()->attach($this->pengendali->id);
    app(PointerTayang::class)->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->polling = app(PollingVerifikasi::class);

    // Operator terikat GELANGGANG, bukan partai -- ia memegang satu matras
    // sepanjang hari (lihat MenjagaAparatGelanggang).
    $this->operator = ($this->buatUser)('operator-it');
    $this->arena->operators()->attach($this->operator->id);

    $this->state = fn () => app(StatePartaiPanel::class)($this->match->fresh(), $this->operator);

    /** Verifikasi yang sudah bulat suaranya, belum diterapkan. */
    $this->bulat = function (
        JenisVerifikasi $jenis = JenisVerifikasi::Jatuhan,
        JawabanVerifikasi $jawaban = JawabanVerifikasi::Merah,
        ?TingkatPelanggaran $tingkat = null,
    ) use (&$v) {
        $verifikasi = $this->polling->minta($this->match, $this->wasit, 1, $jenis, $tingkat);

        $this->polling->jawab($verifikasi, $this->juri[0], $jawaban);

        return $this->polling->jawab($verifikasi, $this->juri[1], $jawaban);
    };
});

/*
 * "Verifikasi juri" sendirian tidak menjawab pertanyaan pertama yang diajukan
 * semua orang di sekitar meja begitu pertandingan berhenti: diverifikasi
 * apanya.
 */
it('menyebutkan apa yang diverifikasi di muatan panel', function () {
    ($this->bulat)(JenisVerifikasi::Jatuhan);

    expect(($this->state)()['verifikasi']['jenis_label'])->toBe('Jatuhan');
});

it('menyebut Pelanggaran untuk verifikasi pelanggaran', function () {
    ($this->bulat)(JenisVerifikasi::Pelanggaran, tingkat: TingkatPelanggaran::Sedang);

    expect(($this->state)()['verifikasi']['jenis_label'])->toBe('Pelanggaran');
});

/*
 * Suara yang sudah bulat BELUM keputusan. Modal baru pantas muncul sesudah
 * Wasit menekan Terapkan.
 */
it('belum menandai diterapkan selama Wasit belum menerapkannya', function () {
    ($this->bulat)();

    $verifikasi = ($this->state)()['verifikasi'];

    expect($verifikasi['hasil'])->toBe('red')
        ->and($verifikasi['sudah_diterapkan'])->toBeFalse();
});

it('menandai diterapkan begitu Wasit menerapkannya', function () {
    $verifikasi = ($this->bulat)();

    $this->polling->terapkan($verifikasi->fresh(), $this->wasit);

    expect(($this->state)()['verifikasi']['sudah_diterapkan'])->toBeTrue();
});

it('membawa hitungan suara tiap sudut untuk digambar inline', function () {
    ($this->bulat)();

    expect(($this->state)()['verifikasi']['hitungan'])->toMatchArray(['red' => 2]);
});

it('menyebut hasil "tidak ada" apa adanya, bukan sebagai sudut', function () {
    $verifikasi = ($this->bulat)(jawaban: JawabanVerifikasi::TidakAda);

    $this->polling->terapkan($verifikasi->fresh(), $this->wasit);

    $muatan = ($this->state)()['verifikasi'];

    expect($muatan['hasil'])->toBe('tidak_ada')
        ->and($muatan['sudah_diterapkan'])->toBeTrue();
});

/*
 * Blok inline tidak lagi mencetak hasilnya. Yang menggambarnya modal, dan
 * modal itu dipasang di panel operator.
 */
it('tidak lagi mencetak blok hasil di dalam blok verifikasi', function () {
    $halaman = $this->actingAs($this->operator)
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk();

    $halaman->assertDontSee('Hasil verifikasi')
        ->assertSee('menunggu Wasit menerapkannya');
});

it('memasang modal hasil di panel operator', function () {
    $this->actingAs($this->operator)
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('hasilVerifikasiTampil', escape: false)
        ->assertSee('judul-hasil-verifikasi', escape: false);
});
