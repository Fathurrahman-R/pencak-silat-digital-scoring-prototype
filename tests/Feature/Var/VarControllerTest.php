<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\AkibatProtes;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Events\Scoring\MatchStateChanged;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\ManagerProtest;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\VarReview;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($kontingen)->create());
    $regBiru = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_BERLANGSUNG, 'current_round' => 1,
    ]);

    $this->ketuaPertandingan = User::factory()->create();
    $this->ketuaPertandingan->syncRoles(['ketua-pertandingan']);
});

it('ketua pertandingan bisa mengajukan dan memutuskan protes VAR lewat HTTP', function () {
    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.var.ajukan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'kejadian' => 'jatuhan tidak dihitung',
        ])->assertOk();

    $review = VarReview::firstOrFail();
    expect($review->corner->value)->toBe('red');

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.var.putuskan', [$this->tournament, $this->match, $review]), [
            'keputusan' => 'sah', 'catatan' => 'dikonfirmasi tayangan ulang',
        ])->assertOk();

    expect($review->fresh()->keputusan)->toBe('sah');
});

it('user tanpa permission var ditolak mengajukan protes', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->postJson(route('admin.turnamen.partai.keberatan.var.ajukan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'kejadian' => 'kejadian',
        ])->assertForbidden();
});

it('mengajukan dan memutuskan protes manajer tingkat pertama lewat HTTP', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'ditolak',
        ])->assertOk();

    expect($protes->fresh()->keputusan)->toBe('ditolak');
});

/*
 * Protes yang DITERIMA wajib menyebut akibatnya -- Pasal 15 ayat 4 huruf c.e.
 *
 * Servernya sudah menegakkannya sejak awal, tapi panel keberatan mengirim
 * keputusan tanpa akibat: tombol "Terima" karena itu tidak pernah bisa
 * berhasil, dan satu-satunya jalan menerima protes adalah lewat tinker.
 */
it('menerima protes manajer beserta akibatnya lewat HTTP', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'diterima', 'akibat' => 'babak_tambahan',
        ])->assertOk();

    expect($protes->fresh())
        ->keputusan->toBe('diterima')
        ->akibat->toBe(AkibatProtes::BabakTambahan);
});

it('menolak menerima protes manajer tanpa akibat lewat HTTP', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'diterima',
        ])->assertStatus(422);

    expect($protes->fresh()->keputusan)->toBeNull();
});

/*
 * Tombol yang tidak bisa berhasil sama buruknya dengan tombol yang tidak ada.
 * Panel harus menawarkan akibatnya SEBELUM tombol Terima ditekan.
 */
it('menawarkan pilihan akibat di panel keberatan', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->get(route('admin.turnamen.partai.keberatan', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Akibat bila diterima')
        ->assertSee('babak_tambahan', false)
        ->assertSee('ubah_hasil', false)
        // Penampilan ulang hanya berarti untuk Jurus.
        ->assertDontSee('penampilan_ulang', false);
});

/*
 * Akibat harus SAMPAI ke panel, bukan berhenti di basis data. "Diterima" tanpa
 * menyebut apa yang harus terjadi berikutnya adalah separuh keputusan bagi
 * yang membacanya di gelanggang -- dan pengesahan hasil tertahan karenanya.
 */
it('menyertakan akibat protes di payload state partai', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'diterima', 'akibat' => 'babak_tambahan',
        ])->assertOk();

    $this->actingAs($this->ketuaPertandingan)
        ->get(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('keberatan.protes_manajer.0.akibat', 'babak_tambahan')
        ->assertJsonPath('keberatan.protes_manajer.0.akibat_label', 'Menambah satu babak')
        ->assertJsonPath('keberatan.protes_manajer.0.akibat_diterapkan', false);
});

/*
 * Protes yang DIAJUKAN harus tersiar, bukan cuma protes yang sudah diputus.
 *
 * Sampai uji lapangan hari ini, hanya keputusan yang menyiarkan
 * MatchStateChanged. Akibatnya: Komisi mengangkat protes VAR lengkap dengan
 * hitung mundur lima menit, dan panel Ketua Pertandingan -- yang justru harus
 * melihat tenggat itu berjalan -- tetap menulis "Belum ada protes VAR" sampai
 * halamannya dimuat ulang dengan tangan. Hal yang sama berlaku untuk protes
 * manajer dan bandingnya.
 */
it('menyiarkan perubahan partai begitu protes VAR diajukan', function () {
    Event::fake([MatchStateChanged::class]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.var.ajukan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'kejadian' => 'jatuhan tidak dihitung',
        ])->assertOk();

    Event::assertDispatched(MatchStateChanged::class);
});

it('menyiarkan perubahan partai begitu protes manajer diajukan', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    Event::fake([MatchStateChanged::class]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    Event::assertDispatched(MatchStateChanged::class);
});

it('menyiarkan perubahan partai begitu banding diajukan', function () {
    $this->match->update(['status' => SilatMatch::STATUS_SELESAI]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.ajukan', [$this->tournament, $this->match]), [
            'catatan' => 'hasil dianggap keliru',
        ])->assertOk();

    $protes = ManagerProtest::firstOrFail();

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.putuskan', [$this->tournament, $this->match, $protes]), [
            'keputusan' => 'ditolak', 'catatan' => 'bukti tidak cukup',
        ])->assertOk();

    Event::fake([MatchStateChanged::class]);

    $this->actingAs($this->ketuaPertandingan)
        ->postJson(route('admin.turnamen.partai.keberatan.protes-manajer.banding', [$this->tournament, $this->match, $protes]), [
            'catatan' => 'naik banding',
        ])->assertOk();

    Event::assertDispatched(MatchStateChanged::class);
});
