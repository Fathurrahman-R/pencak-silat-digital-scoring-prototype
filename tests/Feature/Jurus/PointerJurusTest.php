<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\ArenaTayang;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\PenolakanDapatDipaksa;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Jurus\JurusTimer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Penampilan Jurus mengikuti pengendali, sama seperti partai Tanding.
 *
 * Sebelum ini panel Jurus beralamat per PENAMPILAN. Tiap pergantian nomor
 * menuntut setiap juri membuka alamat baru sendiri-sendiri di HP-nya, di
 * pinggir matras -- persis keadaan yang dulu memindahkan panel Tanding ke
 * alamat gelanggang, dan yang paling merugikan juri: mereka cuma ingin
 * menekan nilai, bukan mengurus navigasi.
 */

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);

    $this->event = $this->tournament->jurusEvents()
        ->where('jenis', JenisJurus::Tunggal)->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)->firstOrFail();

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $daftarJurus = function () use ($kontingen) {
        $registrasi = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['jurus_event_id' => $this->event->id, 'weight_class_id' => null]);
        $registrasi->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $registrasi;
    };

    $this->buatPenampilan = function (int $urutan, ?Arena $arena = null) use ($daftarJurus) {
        return JurusPerformance::create([
            'jurus_event_id' => $this->event->id,
            'registration_id' => $daftarJurus()->id,
            'tahap' => 'final',
            'arena_id' => ($arena ?? $this->arena)->id,
            'order_in_arena' => $urutan,
        ]);
    };

    $this->pertama = ($this->buatPenampilan)(1);
    $this->kedua = ($this->buatPenampilan)(2);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($this->pengendali->id);

    $this->juri = User::factory()->create();
    $this->juri->syncRoles(['juri']);
});

it('menunjuk penampilan lewat panel kendali gelanggang', function () {
    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->pertama->id,
        ])
        ->assertSessionHasNoErrors();

    $tayang = $this->arena->fresh()->tayang;

    expect($tayang->menayangkanJurus())->toBeTrue()
        ->and((int) $tayang->tayang_id)->toBe($this->pertama->id);
});

/*
 * Inti perubahannya: alamat panel juri tidak menyebut penampilan sama sekali,
 * jadi ia tidak pernah basi saat pengendali berpindah nomor.
 */
it('membuka panel juri Jurus lewat alamat gelanggang, tanpa menyebut penampilan', function () {
    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->pertama->id,
        ]);

    $this->actingAs($this->juri)
        ->get(route('admin.turnamen.gelanggang.panel.jurus-juri', [$this->tournament, $this->arena]))
        ->assertOk();
});

it('mengikuti pengendali saat penampilan berpindah, tanpa alamat berubah', function () {
    $alamat = route('admin.turnamen.gelanggang.panel.jurus-state', [$this->tournament, $this->arena]);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->pertama->id,
        ]);

    $this->actingAs($this->juri)->getJson($alamat)
        ->assertOk()
        ->assertJsonPath('penampilan_aktif', true)
        ->assertJsonPath('performance.id', $this->pertama->id);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->kedua->id,
        ]);

    // Alamat yang SAMA, isi yang sudah berpindah.
    $this->actingAs($this->juri)->getJson($alamat)
        ->assertOk()
        ->assertJsonPath('performance.id', $this->kedua->id);
});

/*
 * Satu gelanggang menayangkan satu hal. Satu matras memang cuma bisa dipakai
 * satu hal pada satu waktu, dan pointer yang menampung dua sekaligus akan
 * membuat panel Tanding dan panel Jurus sama-sama mengaku sedang berjalan.
 */
it('melepas partai Tanding saat gelanggang berpindah ke penampilan Jurus', function () {
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bagan = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $partai = SilatMatch::create([
        'bracket_id' => $bagan->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
    ]);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => $partai->id,
        ])
        ->assertSessionHasNoErrors();

    expect($this->arena->fresh()->active_match_id)->toBe($partai->id);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->pertama->id,
        ])
        ->assertSessionHasNoErrors();

    $arena = $this->arena->fresh();

    expect($arena->active_match_id)->toBeNull()
        ->and($arena->tayang->menayangkanJurus())->toBeTrue();
});

it('menolak penampilan yang dijadwalkan di gelanggang lain', function () {
    $lain = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);
    $milikB = ($this->buatPenampilan)(1, $lain);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $milikB->id,
        ])
        ->assertSessionHasErrors('aksi');

    expect($this->arena->fresh()->tayang)->toBeNull();
});

/*
 * Gelanggang kosong adalah keadaan normal -- pagi sebelum nomor pertama, dan
 * jeda antar nomor. Yang membukanya tidak sedang salah alamat.
 */
it('menampilkan layar tunggu saat gelanggang belum menayangkan penampilan', function () {
    $this->actingAs($this->juri)
        ->get(route('admin.turnamen.gelanggang.panel.jurus-juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Menunggu pengendali memilih partai');

    $this->actingAs($this->juri)
        ->getJson(route('admin.turnamen.gelanggang.panel.jurus-state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('penampilan_aktif', false);
});

it('mengosongkan gelanggang yang sedang menayangkan penampilan', function () {
    ArenaTayang::create([
        'arena_id' => $this->arena->id,
        'tayang_type' => ArenaTayang::JURUS,
        'tayang_id' => $this->pertama->id,
        'disetel_pada' => now(),
    ]);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($this->arena->fresh()->tayang->tayang_id)->toBeNull();
});

/*
 * Ditemukan lewat blackbox testing: peran yang MEMINDAHKAN penampilan tidak
 * boleh membuka panel yang memperlihatkannya.
 *
 * `pilihPenampilan` dijaga `kendali-gelanggang.assign`, yang dipegang
 * pengendali; panelnya dijaga `penampilan-jurus.view`, yang tidak. Hasilnya
 * peran yang memutuskan tanpa boleh melihat apa yang sedang diputuskannya --
 * 403 di layar yang seharusnya jadi tempat kerjanya.
 */
it('membiarkan pengendali membuka panel Jurus gelanggangnya', function () {
    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$this->tournament, $this->arena]), [
            'performance_id' => $this->pertama->id,
        ])->assertSessionHasNoErrors();

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.jurus-operator', [$this->tournament, $this->arena]))
        ->assertOk();

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.jurus-state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('penampilan_aktif', true);
});

/*
 * Penampilan yang SEDANG BERJALAN tidak boleh tergusur diam-diam.
 *
 * Satu gelanggang menayangkan satu hal, jadi menayangkan partai Tanding
 * otomatis melepas penampilan Jurus yang sedang tayang. Sampai perbaikan ini,
 * pelepasan itu terjadi tanpa satu pun penolakan: ditemukan di peramban --
 * pengendali menekan Tayangkan pada partai Tanding, `arena_tayang` berpindah,
 * dan juri Jurus tetap memegang panel berisi penampilan yang sudah tidak ada
 * di matras, dengan tombol nilai yang masih hidup.
 *
 * Partai Tanding yang berjalan sudah lama dijaga persis begini. Yang kurang
 * cuma sisi Jurusnya.
 */
it('menolak menggusur penampilan yang sedang berjalan dengan partai Tanding', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);
    app(JurusTimer::class)->mulai($this->pertama);

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);
    $partai = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
    ]);

    expect(fn () => app(PointerTayang::class)->tunjuk($this->arena->fresh(), $partai, $this->pengendali))
        ->toThrow(PenolakanDapatDipaksa::class);

    expect($this->arena->fresh()->tayang->menayangkanJurus())->toBeTrue();
});

it('menggusur penampilan berjalan hanya kalau pengendali menyatakannya paksa', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);
    app(JurusTimer::class)->mulai($this->pertama);

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);
    $partai = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
    ]);

    app(PointerTayang::class)->tunjuk($this->arena->fresh(), $partai, $this->pengendali, paksa: true);

    $tayang = $this->arena->fresh()->tayang;

    /*
     * Statusnya sengaja TIDAK diubah jadi selesai, sama seperti partai Tanding
     * yang ditinggalkan paksa hanya dijeda: menyelesaikannya berarti mencatat
     * durasi penampilan yang sebenarnya tidak pernah selesai dimainkan, dan
     * durasi itulah yang menentukan pengurangan waktu.
     */
    expect($tayang->menayangkanPartai())->toBeTrue()
        ->and($this->pertama->fresh()->status)->toBe(JurusPerformance::STATUS_BERLANGSUNG);
});

it('menolak menggusur penampilan berjalan dengan penampilan lain', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);
    app(JurusTimer::class)->mulai($this->pertama);

    expect(fn () => app(PointerTayang::class)->tunjukPenampilan($this->arena->fresh(), $this->kedua, $this->pengendali))
        ->toThrow(PenolakanDapatDipaksa::class);
});

it('menolak mengosongkan gelanggang yang penampilannya sedang berjalan', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);
    app(JurusTimer::class)->mulai($this->pertama);

    expect(fn () => app(PointerTayang::class)->kosongkan($this->arena->fresh(), $this->pengendali))
        ->toThrow(PenolakanDapatDipaksa::class);

    app(PointerTayang::class)->kosongkan($this->arena->fresh(), $this->pengendali, paksa: true);

    expect($this->arena->fresh()->tayang?->tayang_id)->toBeNull();
});

/*
 * Penampilan yang belum dimulai boleh digusur tanpa upacara. Penjagaan yang
 * berlaku untuk keadaan yang tidak berbahaya cuma melatih pengendali menekan
 * "paksa" tanpa membacanya.
 */
it('membiarkan penampilan yang belum dimulai digusur tanpa paksa', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);

    app(PointerTayang::class)->tunjukPenampilan($this->arena->fresh(), $this->kedua, $this->pengendali);

    expect((int) $this->arena->fresh()->tayang->tayang_id)->toBe($this->kedua->id);
});

/*
 * Panel kendali harus MELIHAT Jurus, bukan cuma bisa memindahkannya.
 *
 * Gelanggang yang menayangkan penampilan Jurus sempat membuat panel
 * pengendalinya menulis "Belum ada partai dipilih" -- seolah matras itu
 * menganggur -- sementara juri Jurus di gelanggang yang sama sedang menatap
 * penampilan yang berjalan. Dari situ pengendali menayangkan partai Tanding di
 * atasnya, dan sampai perbaikan ini tidak ada satu pun penolakan.
 */
it('mengirim tayangan dan antrean Jurus ke panel kendali', function () {
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $this->pertama, $this->pengendali);

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('panel.jurus.tayang.id', $this->pertama->id)
        ->assertJsonCount(2, 'panel.jurus.antrean')
        ->assertJsonPath('panel.jurus.antrean.0.aktif', true);
});

it('menutup antrean Jurus dari petugas yang tidak mengendalikan gelanggang', function () {
    $this->actingAs($this->juri)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonMissingPath('panel.jurus');
});
