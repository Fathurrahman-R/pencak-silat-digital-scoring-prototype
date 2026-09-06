<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
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
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Dashboard tidak berarti apa-apa bagi juri yang duduk di gelanggang yang sama
 * sepanjang hari: satu-satunya yang dicarinya di sana adalah tautan ke
 * panelnya sendiri. Menghapus langkah itu berarti menghapus seluruh navigasi
 * dari pekerjaannya.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create();

    $this->juri = User::factory()->create();
    $this->juri->syncRoles(['juri']);

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);
    $posisi = 0;

    $this->buatPartai = function (Arena $arena, string $status) use ($kelas, $kontingen, $bracket, &$posisi) {
        $daftar = fn () => tap(
            Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
            fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
        );

        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => ++$posisi,
            'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
            'status' => $status,
            'arena_id' => $arena->id, 'order_in_arena' => $posisi,
        ]);
    };
});

it('mendaratkan juri satu gelanggang langsung di panelnya', function () {
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]));
});

/*
 * Yang bertugas di lebih dari satu gelanggang tidak dialihkan: tidak ada dasar
 * memilih salah satunya, dan menebak berarti mendaratkannya di tempat keliru.
 */
it('tidak mengalihkan petugas yang memegang dua gelanggang', function () {
    $kedua = Arena::factory()->for($this->tournament)->create();

    foreach ([$this->arena, $kedua] as $arena) {
        ArenaOfficial::create([
            'arena_id' => $arena->id, 'user_id' => $this->juri->id,
            'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
        ]);
    }

    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});

it('tidak mengalihkan pengguna yang tidak bertugas di gelanggang mana pun', function () {
    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});

/** Pendaratan tidak boleh mengunci: harus ada jalan keluar. */
it('melewati pengalihan lewat ?dashboard=1', function () {
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)
        ->get(route('dashboard', ['dashboard' => 1]))
        ->assertOk();
});

/*
 * Pangkasan flow berlaku untuk SEMUA yang bertugas di gelanggang saat partai
 * berlangsung, bukan hanya juri dan wasit. Dewan Wasit Juri, Wasit Komisi
 * Protes, dan Ketua Pertandingan duduk di kursi yang sama sepanjang hari, dan
 * panelnya sama-sama mengikuti partai aktif gelanggangnya.
 *
 * Pengendali Gelanggang dan Operator IT sengaja tidak ikut: pekerjaan mereka
 * justru mengurus perpindahan, jadi mereka butuh layar yang memandang lebih
 * dari satu partai.
 */
it('mendaratkan seluruh peran gelanggang langsung di panelnya', function (string $peranAparat, string $peranSistem, string $rute) {
    $user = User::factory()->create();
    $user->syncRoles([$peranSistem]);

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $user->id, 'role' => $peranAparat,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route("admin.turnamen.gelanggang.panel.{$rute}", [$this->tournament, $this->arena]));
})->with([
    ['wasit', 'wasit', 'wasit'],
    ['dewan-juri', 'pengawas-wasit-juri', 'dewan-juri'],
    ['komisi-protes', 'wasit-komisi-protes', 'komisi-protes'],
    ['ketua-pertandingan', 'ketua-pertandingan', 'ketua'],
]);

/*
 * Pengalihan tidak boleh mengunci: yang juga memegang peran lain harus tetap
 * bisa mencapai dashboard.
 */
it('membiarkan petugas gelanggang membuka dashboard lewat ?dashboard=1', function () {
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)->get(route('dashboard', ['dashboard' => 1]))->assertOk();
});

/*
 * Dua tabel penugasan bisa berbeda pendapat.
 *
 * `arena_officials` menempatkan seorang juri di satu gelanggang sepanjang
 * hari, sementara `match_officials` masih memegangnya sebagai aparat pada
 * partai yang SEDANG berjalan di gelanggang lain. Terjadi di lapangan:
 * `juri2`, `juri3`, dan `wasit1` tercatat di partai dua gelanggang sekaligus,
 * dan ketiganya didaratkan diam-diam di Gelanggang A padahal dipanggil ke B.
 */
it('tidak mengalihkan petugas yang partai hidupnya ada di gelanggang lain', function () {
    $kedua = Arena::factory()->for($this->tournament)->create();

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $partai = ($this->buatPartai)($kedua, SilatMatch::STATUS_BERLANGSUNG);
    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});

/*
 * Partai TERJADWAL di gelanggang lain tidak memblokir apa pun. Bagan besar
 * menyebar nama yang sama ke dua gelanggang untuk partai yang baru dimainkan
 * sore nanti; kalau itu ikut dihitung, hampir setiap juri terlempar ke
 * dashboard sepanjang hari dan pendaratan langsung kehilangan gunanya.
 */
it('tetap mendaratkan petugas yang partai gelanggang lainnya belum dimainkan', function () {
    $kedua = Arena::factory()->for($this->tournament)->create();

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $partai = ($this->buatPartai)($kedua, SilatMatch::STATUS_TERJADWAL);
    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]));
});

/*
 * Partai yang sedang DITAYANGKAN gelanggang lain ikut dihitung sekalipun
 * statusnya belum berlangsung: begitu pengendali menayangkannya, panel juri
 * di gelanggang itu sudah berpindah ke sana.
 */
it('tidak mengalihkan petugas yang partainya sedang ditayangkan gelanggang lain', function () {
    $kedua = Arena::factory()->for($this->tournament)->create();

    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $partai = ($this->buatPartai)($kedua, SilatMatch::STATUS_TERJADWAL);
    $kedua->update(['active_match_id' => $partai->id]);

    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});
