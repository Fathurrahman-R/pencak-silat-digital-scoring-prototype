<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusBattle;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\PenjadwalJurus;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Jurus\PerbandinganBattle;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Komparasi battle muncul SENDIRI begitu kedua sudut disahkan.
 *
 * Sebelum ini ia cuma ada di satu halaman terpisah yang harus dibuka dengan
 * tangan -- dan yang mengumumkan pemenang di matras adalah operator, yang
 * layarnya tidak pernah menampilkannya. Syaratnya sengaja PENGESAHAN, bukan
 * "sudah tampil": pengurangan Pengawas dijatuhkan sesudah penampilan berhenti,
 * jadi angka yang muncul lebih awal akan berganti di depan penonton.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->nomor = JurusEvent::where('tournament_id', $this->tournament->id)
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $this->nomor->update(['format' => FormatJurus::Battle]);

    $this->daftarkan = function (int $jumlah) {
        return collect(range(1, $jumlah))->map(function () {
            $reg = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => null, 'jurus_event_id' => $this->nomor->id]);
            $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

            return $reg->refresh();
        });
    };

    $this->susun = new SusunBaganJurus;
    $this->penjadwal = new PenjadwalJurus($this->susun);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($this->pengendali->id);

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    /** Battle ronde pertama yang sudah dijadwalkan ke gelanggang. */
    $this->siapkanBattle = function (): JurusBattle {
        ($this->daftarkan)(4);
        $bagan = $this->susun->untukNomor($this->nomor, acak: false);
        $battle = $bagan->battles()->where('round', 1)->first();

        $this->penjadwal->tetapkanBattle($battle, $this->arena);

        return $battle->refresh();
    };

    /** Menilai satu penampilan dengan nilai seragam, lalu mengesahkannya. */
    $this->nilai = function (JurusPerformance $penampilan, float $nilai, bool $sahkan = true) {
        foreach (range(1, 6) as $i) {
            JurusScore::create([
                'performance_id' => $penampilan->id,
                'judge_user_id' => User::factory()->create()->id,
                'value' => $nilai,
            ]);
        }

        $penampilan->update([
            'status' => JurusPerformance::STATUS_SELESAI,
            'duration_ms' => 75_000,
        ] + ($sahkan ? ['ratified_at' => now()] : []));

        return $penampilan->refresh();
    };
});

it('belum siap selama salah satu sudut belum disahkan', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.60, sahkan: false);

    $hasil = app(PerbandinganBattle::class)($battle->refresh());

    expect($hasil['siap'])->toBeFalse();
});

it('siap begitu kedua sudut disahkan', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.60);

    $hasil = app(PerbandinganBattle::class)($battle->refresh());

    expect($hasil['siap'])->toBeTrue()
        ->and($hasil['seri'])->toBeFalse()
        ->and($hasil['selisih'])->toBe(0.1);
});

it('menyatakan seri saat skor akhir kedua sudut sama persis', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.70);

    $hasil = app(PerbandinganBattle::class)($battle->refresh());

    expect($hasil['seri'])->toBeTrue()
        ->and($hasil['selisih'])->toBe(0.0);
});

/*
 * Satu perhitungan, satu jalan masuk: keempat panel yang mengikuti gelanggang
 * menarik endpoint yang SAMA. Dua salinan perhitungan berarti dua angka yang
 * suatu saat berbeda, dan pelatih yang membandingkan dua layar lalu menemukan
 * dua angka punya alasan sah untuk tidak percaya pada keduanya.
 */
it('membawa komparasi di muatan state Jurus gelanggang', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.60);

    app(PointerTayang::class)->tunjukPenampilan($this->arena, $penampilan->last()->refresh(), $this->pengendali);

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.jurus-state', [$this->tournament, $this->arena->refresh()]))
        ->assertOk()
        ->assertJsonPath('komparasi.siap', true);
});

it('tidak membawa komparasi untuk nomor berformat peringkat', function () {
    $this->nomor->update(['format' => FormatJurus::Penampilan]);
    $reg = ($this->daftarkan)(1)->first();

    $penampilan = JurusPerformance::create([
        'jurus_event_id' => $this->nomor->id,
        'registration_id' => $reg->id,
        'tahap' => 'final',
    ]);

    $this->penjadwal->tetapkan($penampilan, $this->arena);
    app(PointerTayang::class)->tunjukPenampilan($this->arena, $penampilan->refresh(), $this->pengendali);

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.jurus-state', [$this->tournament, $this->arena->refresh()]))
        ->assertOk()
        ->assertJsonPath('komparasi', null);
});

it('menggambar komparasi di papan, panel juri, dan panel ketua', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.60);

    app(PointerTayang::class)->tunjukPenampilan($this->arena, $penampilan->last()->refresh(), $this->pengendali);
    $this->arena->refresh();

    foreach ([
        'papan' => 'operator-it',
        'juri' => 'juri',
        'ketua' => 'ketua-pertandingan',
    ] as $rute => $peran) {
        $this->actingAs(($this->buatUser)($peran))
            ->get(route("admin.turnamen.gelanggang.panel.{$rute}", [$this->tournament, $this->arena]))
            ->assertOk()
            ->assertSee('Perbandingan nilai');
    }
});

it('memuat nilai tiap juri dan kedua tingkat pengurangan', function () {
    $battle = ($this->siapkanBattle)();
    $penampilan = $battle->performances()->orderBy('order_in_arena')->get();

    ($this->nilai)($penampilan->first(), 9.70);
    ($this->nilai)($penampilan->last(), 9.60);

    $hasil = app(PerbandinganBattle::class)($battle->refresh());

    expect($hasil['merah']['nilai_juri'])->toHaveCount(6)
        ->and($hasil['merah'])->toHaveKeys(['pengurangan_juri', 'pengurangan_pengawas', 'median', 'akhir']);
});
