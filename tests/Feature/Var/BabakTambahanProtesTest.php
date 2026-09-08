<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\AkibatProtes;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\ManagerProtest;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Babak tambahan dari protes manajer -- Pasal 15 ayat 4 huruf c.e.2.
 *
 * MatchTimer sudah lama mengizinkan satu babak di atas jatah golongan usia
 * bila ada protes manajer yang diterima dengan akibat itu. Yang tidak
 * mengizinkannya adalah validasi di PartaiScoringController, yang menolak
 * babak ke-4 dengan "Partai ini hanya punya 3 babak" sebelum MatchTimer
 * sempat berjalan. Akibatnya keputusan protes tercatat rapi di riwayat tapi
 * tidak pernah bisa dijalankan di gelanggang -- terlihat pada uji lapangan
 * 8 September 2026.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $sudut = collect(['merah', 'biru'])->map(function () use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create());

        return $reg;
    });

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $sudut[0]->id, 'blue_registration_id' => $sudut[1]->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    /** Menjalankan seluruh babak jatah golongan Dewasa sampai selesai. */
    $this->habiskanBabakNormal = function (int $sampai = 3) {
        for ($babak = 1; $babak <= $sampai; $babak++) {
            $this->actingAs($this->pengendali)
                ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/mulai", ['babak' => $babak]);
            $this->actingAs($this->pengendali)
                ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/selesai-babak");
        }
    };
});

it('menolak babak di atas jatah golongan usia tanpa protes yang diterima', function () {
    ($this->habiskanBabakNormal)();

    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/mulai", ['babak' => 4])
        ->assertSessionHasErrors('babak');
});

it('mengizinkan satu babak tambahan sesudah protes manajer diterima', function () {
    ManagerProtest::create([
        'match_id' => $this->match->id,
        'level' => 1,
        'diajukan_at' => now(),
        'tenggat_formulir_at' => now()->addMinutes(10),
        'tenggat_keputusan_at' => now()->addMinutes(30),
        'diputuskan_at' => now(),
        'diputuskan_oleh' => $this->pengendali->id,
        'keputusan' => 'diterima',
        'akibat' => AkibatProtes::BabakTambahan->value,
    ]);

    ($this->habiskanBabakNormal)();

    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/mulai", ['babak' => 4])
        ->assertSessionHasNoErrors();

    expect($this->match->rounds()->where('round', 4)->exists())->toBeTrue();
});

/**
 * Satu babak, dan hanya satu. Berapa pun protes yang diterima, batasnya tidak
 * bergerak -- menjumlahkannya per protes membuka jalan ke partai yang tidak
 * pernah berakhir.
 */
it('tetap menolak babak kelima walau protesnya lebih dari satu', function () {
    foreach (range(1, 2) as $ke) {
        ManagerProtest::create([
            'match_id' => $this->match->id,
            'level' => $ke,
            'diajukan_at' => now(),
            'tenggat_formulir_at' => now()->addMinutes(10),
            'tenggat_keputusan_at' => now()->addMinutes(30),
            'diputuskan_at' => now(),
            'diputuskan_oleh' => $this->pengendali->id,
            'keputusan' => 'diterima',
            'akibat' => AkibatProtes::BabakTambahan->value,
        ]);
    }

    ($this->habiskanBabakNormal)();

    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/mulai", ['babak' => 4])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/selesai-babak");

    $this->actingAs($this->pengendali)
        ->post("/admin/turnamen/{$this->tournament->id}/partai/{$this->match->id}/timer/mulai", ['babak' => 5])
        ->assertSessionHasErrors('babak');
});
