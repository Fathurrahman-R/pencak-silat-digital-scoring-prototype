<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\ResourceAction;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\KesiapanHulu;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Scoring\MatchTimer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Timer dan pergantian jadwal pindah dari Operator IT ke peran baru Pengendali
 * Gelanggang. Berkas ini menjaga tiga hal: wewenangnya benar-benar berpindah,
 * ia terikat gelanggangnya sendiri, dan penugasan aparat per gelanggang tetap
 * meninggalkan catatan per partai.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create();
    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
        'arena_id' => $this->arena->id, 'order_in_arena' => 1,
    ]);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };
});

it('memberi Pengendali Gelanggang wewenang menjalankan timer', function () {
    $pengendali = ($this->buatUser)('pengendali-gelanggang');
    $this->arena->pengendali()->attach($pengendali->id);

    $this->actingAs($pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertSessionHasNoErrors();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

/*
 * Inti perubahannya. Operator IT turun jadi papan tampilan: ia masih boleh
 * MELIHAT panel, tapi tidak lagi memimpin jalannya partai.
 */
it('mencabut wewenang timer dari Operator IT', function () {
    $operator = ($this->buatUser)('operator-it');
    $this->arena->operators()->attach($operator->id);

    $this->actingAs($operator)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_TERJADWAL);
});

it('membiarkan Operator IT tetap membuka panel sebagai papan tampilan', function () {
    $operator = ($this->buatUser)('operator-it');
    $this->arena->operators()->attach($operator->id);

    /*
     * Alamat per-partai sekarang mengantar ke alamat gelanggang: alamat partai
     * basi begitu pengendali memindahkan jadwal, sementara alamat gelanggang
     * mengikuti apa pun yang sedang ditayangkan. Yang diuji di sini bukan
     * pengalihannya -- itu punya berkasnya sendiri -- melainkan bahwa Operator
     * IT tidak kehilangan panelnya di ujung sana.
     */
    $tujuan = route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]);

    $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertRedirect($tujuan);

    $this->actingAs($operator)->get($tujuan)->assertOk();
});

/*
 * Pengendali gelanggang sebelah yang membuka alamat ini bisa menghentikan
 * partai yang bukan urusannya -- taruhannya lebih besar daripada operator,
 * karena ia memegang timer.
 */
it('menolak pengendali gelanggang lain', function () {
    $pengendali = ($this->buatUser)('pengendali-gelanggang');
    $lain = Arena::factory()->for($this->tournament)->create();
    $lain->pengendali()->attach($pengendali->id);

    $this->actingAs($pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();
});

/** Jalan keluar saat perangkat pengendali mati di tengah pertandingan. */
it('membiarkan Ketua Pertandingan mengendalikan timer lintas gelanggang', function () {
    $ketua = ($this->buatUser)('ketua-pertandingan');

    $this->actingAs($ketua)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertSessionHasNoErrors();
});

it('menugaskan pengendali ke gelanggang lewat halaman Gelanggang', function () {
    $admin = ($this->buatUser)(config('resources.super_admin_role'));
    $pengendali = ($this->buatUser)('pengendali-gelanggang');

    $this->actingAs($admin)
        ->post(route('admin.turnamen.gelanggang.pengendali', [$this->tournament, $this->arena]), [
            'pengendali_id' => [$pengendali->id],
        ])
        ->assertSessionHasNoErrors();

    expect($this->arena->fresh()->pengendali->pluck('id')->all())->toBe([$pengendali->id]);
});

it('menolak menugaskan pengguna yang bukan Pengendali Gelanggang', function () {
    $admin = ($this->buatUser)(config('resources.super_admin_role'));
    $juri = ($this->buatUser)('juri');

    $this->actingAs($admin)
        ->post(route('admin.turnamen.gelanggang.pengendali', [$this->tournament, $this->arena]), [
            'pengendali_id' => [$juri->id],
        ])
        ->assertSessionHasErrors('pengendali_id.0');

    expect($this->arena->fresh()->pengendali)->toBeEmpty();
});

/*
 * Penugasan aparat pindah ke gelanggang, tapi catatan siapa bertugas di partai
 * mana harus tetap ada -- berita acara mencetaknya, dan nomor juri dipetakan
 * lewat match_officials.
 */
it('menyalin aparat gelanggang ke partai saat pointer menunjuknya', function () {
    $juri = ($this->buatUser)('juri');
    $wasit = ($this->buatUser)('wasit');
    $pengendali = ($this->buatUser)('pengendali-gelanggang');

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $juri->id, 'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $wasit->id, 'role' => MatchOfficial::ROLE_WASIT, 'number' => null,
    ]);

    (new PointerTayang(new MatchTimer, app(KesiapanHulu::class)))->tunjuk($this->arena, $this->match, $pengendali);

    $aparat = MatchOfficial::where('match_id', $this->match->id)->get();

    expect($aparat)->toHaveCount(2)
        ->and($aparat->firstWhere('user_id', $juri->id)->number)->toBe(1)
        ->and($aparat->firstWhere('user_id', $wasit->id)->role)->toBe(MatchOfficial::ROLE_WASIT);
});

/*
 * Sekretariat yang sengaja menugaskan aparat khusus untuk partai final tidak
 * boleh kehilangan penugasannya hanya karena pengendali menekan tombol pindah.
 */
it('tidak menimpa aparat yang sudah ditugaskan khusus untuk partai itu', function () {
    $juriGelanggang = ($this->buatUser)('juri');
    $juriKhusus = ($this->buatUser)('juri');
    $pengendali = ($this->buatUser)('pengendali-gelanggang');

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $juriGelanggang->id, 'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);
    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $juriKhusus->id, 'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    (new PointerTayang(new MatchTimer, app(KesiapanHulu::class)))->tunjuk($this->arena, $this->match, $pengendali);

    expect(MatchOfficial::where('match_id', $this->match->id)->pluck('user_id')->all())
        ->toBe([$juriKhusus->id]);
});

/*
 * Perintah pemindahan peran harus membuang cache izin sendiri.
 *
 * Tanpa itu ia meninggalkan persis gejala yang dijanjikannya sembuh. Peran
 * sudah tertulis di basis data, tapi gate membaca peta izin yang ter-cache
 * dari sebelum perintah berjalan -- di dalamnya `pengendali-gelanggang` belum
 * ada. Wewenang lama masih lolos, wewenang baru ditolak, dan pengendali
 * membuka panelnya hanya untuk menemukan 403 tanpa penjelasan.
 *
 * Cache sengaja "dipanaskan" lebih dulu di sini, karena begitulah keadaan
 * mesin gelanggang saat perintah dijalankan: aplikasinya sudah melayani
 * permintaan sepanjang hari.
 */
it('membuang cache izin supaya peran baru langsung berlaku', function () {
    $operator = User::factory()->create();
    $operator->syncRoles(['operator-it']);
    $this->arena->operators()->attach($operator->id);

    // Panaskan cache izin dengan peta yang belum mengenal peran baru.
    expect($operator->can(rk('kendali-gelanggang', ResourceAction::View)))->toBeFalse();

    $this->artisan('silat:pindah-pengendali')->assertSuccessful();

    expect($operator->fresh()->can(rk('kendali-gelanggang', ResourceAction::View)))->toBeTrue();
});
