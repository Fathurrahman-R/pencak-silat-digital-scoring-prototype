<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
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

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regMerah->athletes()->attach(Athlete::factory()->for($this->kontingen)->create(['name' => 'Merah Satu']));

    $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $regBiru->athletes()->attach(Athlete::factory()->for($this->kontingen)->create(['name' => 'Biru Satu']));

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->wasit = User::factory()->create();
    $this->wasit->syncRoles(['wasit']);

    $this->juri = User::factory()->count(4)->create();
    $this->juri->each(fn ($u) => $u->syncRoles(['juri']));
});

it('menampilkan halaman penugasan aparat', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.partai.aparat.show', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Merah Satu')
        ->assertSee('Biru Satu');
});

it('menetapkan wasit dan juri sesuai jumlah yang disyaratkan', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => $this->juri->take(3)->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->match->officials()->where('role', MatchOfficial::ROLE_WASIT)->count())->toBe(1)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_JURI)->count())->toBe(3)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_JURI)->pluck('number')->sort()->values()->all())
        ->toBe([1, 2, 3]);
});

it('menolak jumlah juri yang tidak sesuai setelan peraturan', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => $this->juri->take(2)->pluck('id')->all(),
        ])
        ->assertSessionHasErrors('juri_id');
});

it('menolak wasit yang merangkap juri', function () {
    $rangkap = $this->juri->first();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $rangkap->id,
            'juri_id' => $this->juri->take(3)->pluck('id')->all(),
        ])
        ->assertSessionHasErrors('wasit_id');
});

it('menolak juri yang sama ditugaskan dua kali', function () {
    $duplikat = $this->juri->first()->id;

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$duplikat, $duplikat, $this->juri->last()->id],
        ])
        ->assertSessionHasErrors('juri_id.0');
});

it('menimpa penugasan lama saat ditetapkan ulang', function () {
    $this->actingAs($this->admin)->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
        'wasit_id' => $this->wasit->id,
        'juri_id' => $this->juri->take(3)->pluck('id')->all(),
    ]);

    $wasitBaru = User::factory()->create();
    $wasitBaru->syncRoles(['wasit']);

    $this->actingAs($this->admin)->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
        'wasit_id' => $wasitBaru->id,
        'juri_id' => $this->juri->reverse()->take(3)->pluck('id')->all(),
    ]);

    expect($this->match->officials()->count())->toBe(4)
        ->and($this->match->officials()->where('role', MatchOfficial::ROLE_WASIT)->first()->user_id)->toBe($wasitBaru->id);
});

it('menolak partai yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.partai.aparat.show', [$turnamenLain, $this->match]))
        ->assertNotFound();
});

/*
 * --------------------------------------------------------------------
 * Bentrok penugasan antar gelanggang
 * --------------------------------------------------------------------
 */

/** Partai kedua di gelanggang lain, dengan jadwal yang bisa diatur. */
function partaiLain(Tournament $tournament, Contingent $kontingen, ?string $jadwal, string $status = SilatMatch::STATUS_TERJADWAL): SilatMatch
{
    $kelas = $tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'B')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $merah = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $merah->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    $biru = Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $biru->athletes()->attach(Athlete::factory()->for($kontingen)->create());

    return SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'arena_id' => App\Models\Arena::factory()->for($tournament)->create(['name' => 'Gelanggang 2'])->id,
        'red_registration_id' => $merah->id, 'blue_registration_id' => $biru->id,
        'status' => $status,
        'scheduled_at' => $jadwal,
    ]);
}

it('menolak aparat yang sedang bertugas di gelanggang lain pada jam berdekatan', function () {
    /*
     * Satu orang tidak bisa berdiri di dua gelanggang sekaligus. Sampai
     * sekarang tidak ada satu pun pemeriksaan yang menegakkannya: panitia
     * memilih orang yang sedang memimpin partai di sebelah, dan yang ketahuan
     * bukan sistemnya melainkan kursi juri yang kosong saat partai dimulai.
     */
    $this->match->update(['scheduled_at' => '2026-09-01 10:00']);

    $lain = partaiLain($this->tournament, $this->kontingen, '2026-09-01 10:20');
    MatchOfficial::create([
        'match_id' => $lain->id, 'user_id' => $this->juri[0]->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$this->juri[0]->id, $this->juri[1]->id, $this->juri[2]->id],
        ])
        ->assertSessionHasErrors('wasit_id');

    expect($this->match->officials()->count())->toBe(0);
});

it('mengizinkan aparat yang sama pada partai berjam jauh', function () {
    /*
     * Satu orang memang memimpin banyak partai sepanjang hari. Menolak
     * berdasarkan keanggotaan, bukan jadwal, akan memblokir seluruh hari kerja
     * aparat yang sama.
     */
    $this->match->update(['scheduled_at' => '2026-09-01 10:00']);

    $lain = partaiLain($this->tournament, $this->kontingen, '2026-09-01 14:00');
    MatchOfficial::create([
        'match_id' => $lain->id, 'user_id' => $this->juri[0]->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$this->juri[0]->id, $this->juri[1]->id, $this->juri[2]->id],
        ])
        ->assertSessionHasNoErrors();

    expect($this->match->officials()->count())->toBe(4);
});

it('menolak aparat yang partainya sedang berlangsung, berapa pun jadwal tertulisnya', function () {
    $this->match->update(['scheduled_at' => '2026-09-01 10:00']);

    $lain = partaiLain($this->tournament, $this->kontingen, '2026-09-01 07:00', SilatMatch::STATUS_BERLANGSUNG);
    MatchOfficial::create([
        'match_id' => $lain->id, 'user_id' => $this->wasit->id, 'role' => MatchOfficial::ROLE_WASIT,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$this->juri[0]->id, $this->juri[1]->id, $this->juri[2]->id],
        ])
        ->assertSessionHasErrors('wasit_id');
});

it('membawa sebab bentrok ke halaman penugasan, bukan menghapus namanya dari daftar', function () {
    /*
     * Menghapus yang bentrok dari daftar akan membuat panitia yang mencari
     * nama dan tidak menemukannya mengira orangnya belum terdaftar, lalu
     * membuat akun kedua.
     */
    $this->match->update(['scheduled_at' => '2026-09-01 10:00']);

    $lain = partaiLain($this->tournament, $this->kontingen, '2026-09-01 10:15');
    MatchOfficial::create([
        'match_id' => $lain->id, 'user_id' => $this->juri[0]->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 2,
    ]);

    $halaman = $this->actingAs($this->admin)
        ->get(route('admin.turnamen.partai.aparat.show', [$this->tournament, $this->match]))
        ->assertOk();

    $bentrok = $halaman->viewData('bentrok');

    expect($bentrok)->toHaveKey($this->juri[0]->id)
        ->and($bentrok[$this->juri[0]->id])->toContain('Gelanggang 2')
        ->and($bentrok[$this->juri[0]->id])->toContain('Juri 2');

    // Namanya tetap ada di daftar pilihan.
    expect($halaman->viewData('juriTersedia')->keys()->all())->toContain($this->juri[0]->id);
});

it('tidak menyatakan bentrok saat salah satu partai belum terjadwal', function () {
    /*
     * Menugaskan aparat sebelum jadwalnya disusun adalah urutan kerja yang
     * lazim; menolaknya akan menghalangi panitia bekerja.
     */
    $this->match->update(['scheduled_at' => null]);

    $lain = partaiLain($this->tournament, $this->kontingen, null);
    MatchOfficial::create([
        'match_id' => $lain->id, 'user_id' => $this->juri[0]->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.partai.aparat.store', [$this->tournament, $this->match]), [
            'wasit_id' => $this->wasit->id,
            'juri_id' => [$this->juri[0]->id, $this->juri[1]->id, $this->juri[2]->id],
        ])
        ->assertSessionHasNoErrors();
});
