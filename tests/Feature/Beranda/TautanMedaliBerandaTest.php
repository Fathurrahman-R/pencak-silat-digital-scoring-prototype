<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Enums\StatusTurnamen;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;

/**
 * Blok perolehan medali di beranda hanya muncul setelah ada final yang
 * disahkan -- dan justru karena itu tautannya bisa salah tanpa ketahuan
 * sepanjang pengembangan. Nama rutenya sempat ditulis `live.medali`,
 * padahal yang terdaftar `live.turnamen.medali`, jadi beranda baru
 * meledak dengan RouteNotFoundException pada partai final pertama yang
 * disahkan -- persis di tengah kejuaraan.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create([
        'starts_on' => '2026-09-01',
        'status' => StatusTurnamen::Berjalan,
    ]);
    (new SusunMasterDataTurnamen)($this->tournament);
});

function sahkanSatuFinal(Tournament $tournament): void
{
    $kontingen = Contingent::factory()->for($tournament)->create();
    $kelas = $tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $peserta = collect(['Juara Satu', 'Juara Dua'])->map(function (string $nama) use ($kontingen, $kelas) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create(['name' => $nama]));

        return $reg;
    });

    SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $peserta[0]->id, 'blue_registration_id' => $peserta[1]->id,
        'winner_registration_id' => $peserta[0]->id, 'win_reason' => 'angka',
        'status' => SilatMatch::STATUS_SELESAI,
        'ratified_at' => now(), 'ratified_by' => User::factory()->create()->id,
    ]);
}

it('menautkan blok perolehan medali ke halaman medali publik', function () {
    sahkanSatuFinal($this->tournament);

    $this->get('/')
        ->assertOk()
        ->assertSee('Perolehan medali')
        ->assertSee(route('live.turnamen.medali', $this->tournament), false);
});

it('tidak merender blok medali sebelum ada final yang disahkan', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Perolehan medali');
});
