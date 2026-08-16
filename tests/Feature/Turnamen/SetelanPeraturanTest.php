<?php

use App\Enums\GolonganUsia;
use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create();
    TournamentRuleSetting::factory()->for($this->tournament)->create();
});

/** Formulir yang sah, dipakai sebagai dasar lalu diubah per kasus uji. */
function setelanSah(array $ganti = []): array
{
    $babak = [];
    foreach (GolonganUsia::cases() as $golongan) {
        if ($golongan->adaTanding()) {
            $babak[$golongan->value] = ['jumlah' => 3, 'durasi_detik' => 120];
        }
    }

    return array_replace_recursive([
        'jumlah_juri_tanding' => 3,
        'ambang_sepakat' => 2,
        'window_konsensus_ms' => 2000,
        'jumlah_juri_jurus' => 6,
        'istirahat_detik' => 60,
        'nilai' => ['pukulan' => 1, 'tendangan' => 2, 'jatuhan' => 3],
        'hukuman' => [
            'pembinaan_ambang' => 2,
            'teguran' => [1 => -1, 2 => -2],
            'peringatan' => [1 => -5, 2 => -10],
        ],
        'babak' => $babak,
        'wmp' => [
            'bawaan' => ['selisih' => 30, 'mulai_babak' => 2],
            'usia_dini_1' => ['selisih' => 20, 'mulai_babak' => 1],
            'usia_dini_2' => ['selisih' => 20, 'mulai_babak' => 1],
        ],
        'kartu_protes_tanding' => 2,
        'kartu_protes_jurus' => 1,
        'tenggat_var_detik' => 300,
    ], $ganti);
}

it('menampilkan formulir setelan peraturan', function () {
    $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/peraturan")
        ->assertOk()
        ->assertSee('Tidak diatur naskah')
        ->assertSee('Pasal 16 ayat 1 huruf a', false);
});

it('menyimpan setelan dan menerjemahkan detik ke milidetik', function () {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'istirahat_detik' => 90,
            'babak' => ['dewasa' => ['jumlah' => 3, 'durasi_detik' => 150]],
        ]))
        ->assertSessionHasNoErrors();

    $setelan = $this->tournament->peraturan()->fresh();

    expect($setelan->istirahat_ms)->toBe(90_000)
        ->and($setelan->babakUntuk(GolonganUsia::Dewasa))
        ->toBe(['jumlah' => 3, 'durasi_ms' => 150_000]);
});

/*
 * Ambang yang melebihi jumlah juri berarti tidak ada nilai yang bisa terbit
 * sama sekali. Pertandingan tetap berjalan, papan skornya diam terus, dan
 * tidak ada yang tahu penyebabnya sampai babak berakhir 0-0.
 */
it('menolak ambang sepakat yang melebihi jumlah juri', function () {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'jumlah_juri_tanding' => 3,
            'ambang_sepakat' => 4,
        ]))
        ->assertSessionHasErrors('ambang_sepakat');
});

// Pasal 16.1.b: median diambil dari rata-rata dua nilai tengah, jadi jumlah
// jurinya harus genap.
it('menolak jumlah juri jurus yang ganjil', function () {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'jumlah_juri_jurus' => 5,
        ]))
        ->assertSessionHasErrors('jumlah_juri_jurus');
});

it('menolak tangga hukuman yang tidak menurun', function (string $jenis, array $pengurangan) {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'hukuman' => [$jenis => $pengurangan],
        ]))
        ->assertSessionHasErrors("hukuman.{$jenis}.2");
})->with([
    'teguran mendatar' => ['teguran', [1 => -2, 2 => -2]],
    'teguran terbalik' => ['teguran', [1 => -3, 2 => -1]],
    'peringatan terbalik' => ['peringatan', [1 => -10, 2 => -5]],
]);

/*
 * Urutan nilai teknik juga dipakai sebagai pemecah seri (Pasal 11.6.g.1.b),
 * jadi membaliknya tidak cuma salah angka — ia membalik siapa yang menang saat
 * skornya sama.
 */
it('menolak urutan nilai teknik yang tidak menaik', function () {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'nilai' => ['pukulan' => 3, 'tendangan' => 2, 'jatuhan' => 1],
        ]))
        ->assertSessionHasErrors('nilai.jatuhan');
});

it('mempertahankan cakupan dan jumlah kolom hukuman dari naskah', function () {
    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah([
            'hukuman' => ['teguran' => [1 => -2, 2 => -4]],
        ]));

    $hukuman = $this->tournament->peraturan()->fresh()->hukuman;

    expect($hukuman['teguran']['pengurangan'])->toBe([1 => -2, 2 => -4])
        ->and($hukuman['teguran']['cakupan'])->toBe('babak')
        ->and($hukuman['peringatan']['cakupan'])->toBe('partai')
        ->and($hukuman['peringatan']['jumlah_kolom'])->toBe(3)
        ->and($hukuman['peringatan']['tingkat_diskualifikasi'])->toBe(3)
        ->and($hukuman['peringatan']['pengurangan'][3])->toBeNull();
});

it('mengembalikan setelan ke angka naskah', function () {
    $this->tournament->peraturan()->update(['ambang_sepakat' => 1, 'window_konsensus_ms' => 5000]);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/peraturan/reset")
        ->assertSessionHasNoErrors();

    expect($this->tournament->peraturan()->fresh())
        ->ambang_sepakat->toBe(config('scoring.juri.tanding.ambang_sepakat'))
        ->window_konsensus_ms->toBe(config('scoring.juri.tanding.window_ms'));
});

/*
 * Penguncian ditegakkan di server, bukan sekadar dengan menyembunyikan
 * formulirnya. Kalau hanya tampilan yang dikunci, satu permintaan yang disusun
 * tangan sudah cukup untuk mengubah dasar perhitungan pertandingan yang sedang
 * berjalan — termasuk partai yang hasilnya sudah disahkan.
 */
it('menolak menyunting setelan kejuaraan yang sudah berjalan', function () {
    $this->tournament->update(['status' => 'berjalan']);

    $this->actingAs($this->admin)
        ->put("/admin/turnamen/{$this->tournament->id}/peraturan", setelanSah(['ambang_sepakat' => 1]))
        ->assertForbidden();

    expect($this->tournament->peraturan()->fresh()->ambang_sepakat)->toBe(2);
});

it('menolak mengembalikan setelan kejuaraan yang sudah berjalan', function () {
    $this->tournament->update(['status' => 'berjalan']);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/peraturan/reset")
        ->assertForbidden();
});

it('menutup setelan peraturan dari pengguna tanpa hak akses', function () {
    $tanpaHak = User::factory()->create();

    $this->actingAs($tanpaHak)
        ->get("/admin/turnamen/{$this->tournament->id}/peraturan")
        ->assertForbidden();
});
