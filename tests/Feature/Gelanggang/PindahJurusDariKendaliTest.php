<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\PenjadwalJurus;
use App\Support\Bagan\SusunBaganJurus;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Memindahkan penampilan Jurus ke gelanggang lain, dari panel kendali.
 *
 * Sampai uji kotak hitam 10 September 2026, antrean Jurus di panel kendali
 * hanya punya tombol "Tayangkan" -- sementara antrean Tanding tepat di atasnya
 * punya "Pindahkan…". Seluruh sisi servernya sudah ada: endpoint menerima
 * `jenis`, PointerTayang menjaga baris yang sedang ditawarkan, dan rancangan
 * serah-terima menyebut kedua kategori. Yang tidak ada cuma pintunya.
 *
 * Satu sudut battle TIDAK ditawari pindah. Serah-terima memindahkan satu
 * baris, dan kedua sudut battle dimainkan berurutan di matras yang sama
 * (Pasal 12.1.d.7) -- yang tertinggal akan berdiri sendirian di antrean tanpa
 * penjelasan. Tombol yang pasti ditolak lebih buruk daripada tombol yang tidak
 * ada, jadi yang tampil kalimat yang menyebut ke mana harus pergi.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->nomor = JurusEvent::where('tournament_id', $this->tournament->id)
        ->where('jenis', JenisJurus::Tunggal)
        ->where('golongan_usia', GolonganUsia::Dewasa)
        ->where('jenis_kelamin', JenisKelamin::Putra)
        ->firstOrFail();

    $this->daftarkan = function (int $jumlah) {
        return collect(range(1, $jumlah))->map(function () {
            $reg = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => null, 'jurus_event_id' => $this->nomor->id]);
            $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

            return $reg->refresh();
        });
    };

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arenaA->pengendali()->attach($this->pengendali->id);

    $this->penjadwal = new PenjadwalJurus(new SusunBaganJurus);

    $this->kendali = fn () => $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arenaA]));

    $this->muatan = fn () => $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arenaA]));
});

it('menawarkan Pindahkan untuk penampilan lepas, bukan hanya Tayangkan', function () {
    $this->nomor->update(['format' => FormatJurus::Penampilan]);
    $reg = ($this->daftarkan)(1)->first();

    $penampilan = JurusPerformance::create([
        'jurus_event_id' => $this->nomor->id,
        'registration_id' => $reg->id,
        'tahap' => 'final',
    ]);

    $this->penjadwal->tetapkan($penampilan, $this->arenaA);

    ($this->kendali)()
        ->assertOk()
        ->assertSee('Pindahkan…', escape: false)
        ->assertSee("lepasKeGelanggang(satu.id, \$event.target.value, 'jurus')", escape: false);
});

/*
 * Penanda inilah yang dipakai panel untuk memutuskan barisnya boleh dipindah
 * atau tidak. Tanpa ia di muatan, panel tidak punya cara membedakan.
 */
it('menyebutkan battle induk tiap baris antrean di muatan panel', function () {
    $this->nomor->update(['format' => FormatJurus::Battle]);
    ($this->daftarkan)(2);

    $bagan = (new SusunBaganJurus)->untukNomor($this->nomor->fresh(), acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->penjadwal->tetapkanBattle($battle, $this->arenaA);

    $antrean = ($this->muatan)()->assertOk()->json('panel.jurus.antrean');

    expect($antrean)->toHaveCount(2);

    foreach ($antrean as $baris) {
        expect($baris)->toHaveKey('battle')
            ->and($baris['battle'])->toBe($battle->id);
    }
});

it('tidak menawarkan Pindahkan untuk satu sudut battle, dan menyebut jalan keluarnya', function () {
    $this->nomor->update(['format' => FormatJurus::Battle]);
    ($this->daftarkan)(2);

    $bagan = (new SusunBaganJurus)->untukNomor($this->nomor->fresh(), acak: false);
    $this->penjadwal->tetapkanBattle($bagan->battles()->where('round', 1)->first(), $this->arenaA);

    ($this->kendali)()
        ->assertOk()
        // Syarat `! satu.battle` yang menahannya, dan kalimat penggantinya.
        ->assertSee('! satu.battle', escape: false)
        ->assertSee('pindahkan lewat menu Jadwal', escape: false);
});

/*
 * Sisi server tetap menolak, apa pun yang digambar panel: pintu yang ditutup
 * di layar bukan pintu yang terkunci.
 */
it('menolak permintaan pindah satu sudut battle sekalipun dikirim langsung', function () {
    $this->nomor->update(['format' => FormatJurus::Battle]);
    ($this->daftarkan)(2);

    $bagan = (new SusunBaganJurus)->untukNomor($this->nomor->fresh(), acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();
    $this->penjadwal->tetapkanBattle($battle, $this->arenaA);

    $sudut = JurusPerformance::where('jurus_battle_id', $battle->id)->firstOrFail();

    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.gelanggang.panel.lepas', [$this->tournament, $this->arenaA]), [
            'ke_arena_id' => $this->arenaB->id,
            'jenis' => 'jurus',
            'baris_id' => $sudut->id,
        ])
        ->assertStatus(422);
});
