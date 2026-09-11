<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\ArenaTayang;
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
    ['wasit', 'ketua-pertandingan', 'wasit'],
    ['dewan-juri', 'ketua-pertandingan', 'dewan-juri'],
    ['komisi-protes', 'ketua-pertandingan', 'komisi-protes'],
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
    ArenaTayang::updateOrCreate(
        ['arena_id' => $kedua->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $partai->id, 'disetel_pada' => now()],
    );

    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});

/*
 * Panel juri dan wasit dijaga penugasan PER PARTAI, sementara pendaratan ini
 * membaca penugasan per gelanggang. Kedua tabel bisa berbeda -- aparat
 * gelanggang disalin ke partai hanya saat pengendali menunjuknya, dan
 * penyalinan itu tidak menimpa aparat yang sudah ditugaskan khusus. Terjadi di
 * lapangan: `wasit2` didaratkan di panel wasit Gelanggang B, lalu disambut
 * "Anda tidak ditugaskan sebagai aparat pada partai ini".
 */
it('tidak mendaratkan petugas di panel yang partainya bukan tugasnya', function () {
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $partai = ($this->buatPartai)($this->arena, SilatMatch::STATUS_BERLANGSUNG);
    ArenaTayang::updateOrCreate(
        ['arena_id' => $this->arena->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $partai->id, 'disetel_pada' => now()],
    );

    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => User::factory()->create()->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)->get(route('dashboard'))->assertOk();
});

it('tetap mendaratkan petugas yang memang aparat partai yang sedang tayang', function () {
    ArenaOfficial::create([
        'arena_id' => $this->arena->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $partai = ($this->buatPartai)($this->arena, SilatMatch::STATUS_BERLANGSUNG);
    ArenaTayang::updateOrCreate(
        ['arena_id' => $this->arena->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $partai->id, 'disetel_pada' => now()],
    );

    MatchOfficial::create([
        'match_id' => $partai->id, 'user_id' => $this->juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->juri)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]));
});

/*
 * Pengendali Gelanggang dan Operator IT sengaja tidak dialihkan (lihat blok di
 * atas) -- tapi selama ini mereka juga tidak diberi tautan apa pun. Nama rute
 * panel kendali tidak pernah dirujuk satu kali pun di luar definisinya, dan
 * kartu penugasan di dashboard hanya menyusun alamat juri/wasit dari
 * `match_officials`, tabel yang tidak pernah menyebut kedua peran ini.
 *
 * Hasilnya: keduanya mendarat di dashboard tanpa satu pun jalan ke panelnya,
 * dan satu-satunya cara masuk adalah mengetik alamat.
 */
it('menautkan pengendali ke panel kendali gelanggang yang dipegangnya', function () {
    $pengendali = User::factory()->create();
    $pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($pengendali);

    $this->actingAs($pengendali)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($this->arena->name)
        ->assertSee(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]), false);
});

it('menautkan operator ke papan tampilan gelanggang yang dipegangnya', function () {
    $operator = User::factory()->create();
    $operator->syncRoles(['operator-it']);
    $this->arena->operators()->attach($operator);

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]), false);
});

/*
 * Justru inilah alasan keduanya tidak dialihkan: yang memegang dua gelanggang
 * butuh melihat keduanya sekaligus. Kartunya harus menyebut semuanya, bukan
 * memilih satu.
 */
it('menyebut seluruh gelanggang yang dipegang pengendali, bukan salah satunya', function () {
    $kedua = Arena::factory()->for($this->tournament)->create();

    $pengendali = User::factory()->create();
    $pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($pengendali);
    $kedua->pengendali()->attach($pengendali);

    $this->actingAs($pengendali)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]), false)
        ->assertSee(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $kedua]), false);
});

/* Gelanggang yang dinonaktifkan tidak lagi dipakai; menautkannya menyesatkan. */
it('tidak menautkan gelanggang yang sudah dinonaktifkan', function () {
    $pengendali = User::factory()->create();
    $pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($pengendali);
    $this->arena->update(['is_active' => false]);

    $this->actingAs($pengendali)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]), false);
});

it('tidak menampilkan kartu gelanggang bagi yang tidak memegang satu pun', function () {
    $this->actingAs($this->juri)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Gelanggang yang kamu pegang');
});
