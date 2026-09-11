<?php

use App\Enums\ResourceAction;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Spatie\Permission\Models\Role;

/*
 * Tiga peran matras lebur ke Ketua Pertandingan, September 2026: Wasit,
 * Dewan Wasit Juri (Pengawas), dan Wasit Komisi Protes.
 *
 * Peleburan peran punya satu cara gagal yang tidak bersuara: peran lamanya
 * hilang, sebagian kewenangannya ikut hilang bersamanya, dan yang ketahuan
 * bukan daftar peran melainkan panel yang membalas 403 di pinggir matras saat
 * partai sudah berjalan. Dua contoh nyatanya ada di peleburan ini:
 *
 *   - `hukuman.view` yang membuka `panel/wasit`, yang tidak dimiliki Ketua
 *     sebelumnya sama sekali;
 *   - `pengurangan-jurus.create`, satu-satunya kewenangan Dewan Wasit Juri
 *     yang belum dipegang Ketua.
 *
 * Daftar di bawah ditulis tangan dari ketiga blok peran sebagaimana adanya
 * sebelum dibubarkan. Ia sengaja TIDAK dibaca dari seeder: yang diuji justru
 * apakah seeder masih memberikan semuanya.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->ketua = User::factory()->create();
    $this->ketua->syncRoles(['ketua-pertandingan']);
});

it('memberi Ketua Pertandingan seluruh bekas kewenangan tiga peran matras', function () {
    $bekas = [
        // Wasit.
        ['partai', ResourceAction::View],
        ['partai', ResourceAction::Update],
        ['hukuman', ResourceAction::View],
        ['hukuman', ResourceAction::Create],
        ['penilaian', ResourceAction::View],
        ['verifikasi-juri', ResourceAction::Create],
        ['verifikasi-juri', ResourceAction::Approve],
        ['verifikasi-juri', ResourceAction::Reject],

        // Dewan Wasit Juri.
        ['penugasan-aparat', ResourceAction::Assign],
        ['hasil-partai', ResourceAction::View],
        ['hasil-partai', ResourceAction::Update],
        ['hasil-partai', ResourceAction::Approve],
        ['hasil-partai', ResourceAction::Print],
        ['penampilan-jurus', ResourceAction::View],
        ['pengurangan-jurus', ResourceAction::Create],
        ['hasil-jurus', ResourceAction::Update],

        // Wasit Komisi Protes.
        ['var', ResourceAction::View],
        ['var', ResourceAction::Create],
        ['var', ResourceAction::Approve],
    ];

    $hilang = [];

    foreach ($bekas as [$sumber, $aksi]) {
        $kunci = rk($sumber, $aksi);

        if (! $this->ketua->can($kunci)) {
            $hilang[] = $kunci;
        }
    }

    expect($hilang)->toBe([], 'kewenangan yang tidak ikut pindah: '.implode(', ', $hilang));
});

/*
 * Bukan cuma punya key-nya, tapi sampai ke layarnya. Tiap panel matras dijaga
 * key yang berbeda, dan satu saja yang tertinggal berarti satu kursi yang
 * tidak bisa diduduki siapa pun.
 */
it('membiarkan Ketua Pertandingan membuka keempat panel matras', function () {
    $pintu = [
        'panel/wasit' => rk('hukuman', ResourceAction::View),
        'panel/dewan-juri' => rk('hasil-partai', ResourceAction::View),
        'panel/komisi-protes' => rk('var', ResourceAction::View),
        'panel/ketua' => rk('partai', ResourceAction::View),
    ];

    foreach ($pintu as $panel => $kunci) {
        expect($this->ketua->can($kunci))->toBeTrue("panel {$panel} tertutup: {$kunci}");
    }
});

it('tidak menyisakan peran matras yang kosong', function () {
    foreach (['wasit', 'pengawas-wasit-juri', 'wasit-komisi-protes'] as $lama) {
        expect(Role::where('name', $lama)->exists())->toBeFalse("peran {$lama} masih ada");
    }
});

/*
 * Yang TIDAK ikut lebur: Juri. Ia menilai, dan yang dinilainya diadu dengan
 * penilaian juri lain -- peran yang sekaligus menilai dan mengesahkan
 * hasilnya sendiri membuat konsensus kehilangan artinya.
 */
it('tidak menelan peran Juri', function () {
    expect(Role::where('name', 'juri')->exists())->toBeTrue();

    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    expect($juri->can(rk('penilaian', ResourceAction::Create)))->toBeTrue()
        ->and($juri->can(rk('hasil-partai', ResourceAction::Approve)))->toBeFalse();
});

/*
 * Pemegang peran lama ikut pindah, bukan ditinggalkan tanpa kewenangan. Uji
 * ini menirukan pemasangan yang sudah jalan: perannya dibuat lagi, diberi
 * seorang pemegang, lalu seedernya dijalankan ulang.
 */
it('memindahkan pemegang ketiga peran lama ke Ketua Pertandingan', function () {
    $pemegang = [];

    foreach (['wasit', 'pengawas-wasit-juri', 'wasit-komisi-protes'] as $lama) {
        Role::create(['name' => $lama, 'guard_name' => 'web']);

        $orang = User::factory()->create();
        $orang->syncRoles([$lama]);
        $pemegang[$lama] = $orang;
    }

    $this->seed(SilatRoleSeeder::class);

    foreach ($pemegang as $lama => $orang) {
        expect(Role::where('name', $lama)->exists())->toBeFalse("peran {$lama} tidak dibubarkan")
            ->and($orang->fresh()->hasRole('ketua-pertandingan'))->toBeTrue("pemegang {$lama} tidak dipindahkan");
    }
});
