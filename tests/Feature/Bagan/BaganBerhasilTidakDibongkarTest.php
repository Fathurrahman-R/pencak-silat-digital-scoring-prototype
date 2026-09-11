<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\BracketGenerator;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Bagan yang partainya sudah punya hasil tidak boleh dibongkar.
 *
 * Penyusunan ulang menghapus bagan lama beserta seluruh partainya, dan bersama
 * partainya ikut hilang nilai, hukuman, pemenang, dan medali yang sudah
 * diumumkan. Sebelum ini satu-satunya penjagaan adalah kunci bagan -- dan
 * kuncinya bisa dibuka siapa pun yang menuliskan alasan, tanpa satu pun
 * pemeriksaan terhadap partai yang sudah dinilai. Terlihat pada uji lapangan
 * 8 September 2026: kelas yang seluruh partainya sudah disahkan dan medalinya
 * sudah terbit tetap bisa dibuka dan disusun ulang.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create();
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    foreach (range(1, 2) as $ke) {
        $reg = Registration::factory()->for($kontingen)->terverifikasi()
            ->create(['weight_class_id' => $this->kelas->id]);
        $reg->athletes()->attach(Athlete::factory()->for($kontingen)->create());
    }

    $this->bracket = app(BracketGenerator::class)->untukKelas($this->kelas);
});

it('membolehkan menyusun ulang selama belum ada partai yang dinilai', function () {
    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}/susun", ['mode' => 'gugur'])
        ->assertSessionHasNoErrors();

    expect($this->kelas->bracket()->first())->not->toBeNull();
});

it('menolak membuka kunci bagan yang partainya sudah disahkan', function () {
    $partai = $this->bracket->matches()->firstOrFail();
    $partai->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $partai->red_registration_id,
        'ratified_at' => now(),
    ]);

    app(BracketGenerator::class)->kunci($this->bracket, $this->admin);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}/buka-kunci", [
            'alasan' => 'Undiannya keliru',
        ])
        ->assertSessionHas('error');

    expect($this->bracket->fresh()->terkunci())->toBeTrue();
});

/**
 * Penjagaan yang sesungguhnya berdiri di tempat penghapusan terjadi, bukan
 * cuma di tombol buka kunci: bagan yang kebetulan belum terkunci sekali pun
 * tidak boleh kehilangan partainya.
 */
it('menolak menyusun ulang bagan yang partainya sudah punya pemenang walau tidak terkunci', function () {
    $partai = $this->bracket->matches()->firstOrFail();
    $partai->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $partai->red_registration_id,
    ]);

    $jumlahSebelum = $this->bracket->matches()->count();

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}/susun", ['mode' => 'gugur'])
        ->assertSessionHas('error');

    expect($this->kelas->bracket()->first()->id)->toBe($this->bracket->id)
        ->and($this->bracket->matches()->count())->toBe($jumlahSebelum)
        ->and($partai->fresh()->winner_registration_id)->not->toBeNull();
});
