<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\ArenaOfficial;
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

/**
 * Aparat hanya berwenang MENULIS SKOR pada partai yang memang ditugaskan
 * kepadanya. Izin peran saja tidak cukup: dua gelanggang berjalan bersamaan,
 * dan aparat gelanggang sebelah tidak boleh ikut menilai atau menghukum di
 * sini -- angkanya tayang di layar besar.
 *
 * Yang dihitung sebagai "ditugaskan": baris `match_officials` partai ini, ATAU
 * kursi di `arena_officials` gelanggang tempat partai ini dimainkan. Yang
 * kedua ada karena penugasan memang hidup di gelanggang sejak September 2026,
 * dan salinannya ke partai baru lahir saat pengendali menunjuknya.
 *
 * MEMBUKA panel tidak dijaga sama sekali: membuka tidak mengubah apa pun, dan
 * panitia tidak mau dihadang saat mencari layar.
 */
beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->match = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        return $user;
    };

    $this->pengendali = $buatUser('pengendali-gelanggang');
    // Operator IT masih dibuat: ia dipakai membuktikan wewenang timernya
    // memang sudah dicabut, bukan sekadar berpindah tangan.
    $this->operator = $buatUser('operator-it');
    $this->pengawas = $buatUser('pengawas-wasit-juri');
    $this->ketua = $buatUser('ketua-pertandingan');

    /*
     * Partai dimainkan di Gelanggang A, dan pengendali bawaan test ini memang
     * pengendalinya. Gelanggang B beserta pengendalinya baru dibuat oleh test
     * yang memerlukannya.
     */
    $this->gelanggangA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $this->gelanggangA->pengendali()->attach($this->pengendali);
    $this->gelanggangA->operators()->attach($this->operator);
    $this->match->update(['arena_id' => $this->gelanggangA->id, 'order_in_arena' => 1]);

    $this->siapkanGelanggang = function () use ($buatUser) {
        $gelanggangB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B']);
        $pengendaliB = $buatUser('pengendali-gelanggang');
        $gelanggangB->pengendali()->attach($pengendaliB);

        $this->gelanggangA->pengendali = $this->pengendali;
        $gelanggangB->pengendali = $pengendaliB;

        return [$this->gelanggangA, $gelanggangB];
    };

    $this->juriDitugaskan = $buatUser('juri');
    $this->juriGelanggangLain = $buatUser('juri');
    $this->wasitDitugaskan = $buatUser('wasit');
    $this->wasitGelanggangLain = $buatUser('wasit');

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->wasitDitugaskan->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);
    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->juriDitugaskan->id,
        'role' => MatchOfficial::ROLE_JURI,
        'number' => 1,
    ]);

    $this->mulaiBabak = function () {
        $this->actingAs($this->pengendali)->post(
            route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]),
            ['babak' => 1],
        );
    };
});

it('menolak nilai dari juri yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juriGelanggangLain)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])
        ->assertForbidden();

    expect($this->match->judgeInputs()->count())->toBe(0);
});

it('menerima nilai dari juri yang ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->juriDitugaskan)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])
        ->assertRedirect();

    expect($this->match->judgeInputs()->count())->toBe(1);
});

it('menolak hukuman dari wasit yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitGelanggangLain)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertForbidden();

    expect($this->match->penalties()->count())->toBe(0);
});

it('menerima hukuman dari wasit yang ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitDitugaskan)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

/*
 * Dewan Wasit Juri tidak lagi dikecualikan lewat PERANNYA -- ia lebur ke Ketua
 * Pertandingan (September 2026), dan peran itu juga dipakai wasit tiap matras,
 * jadi mengecualikannya berarti mengecualikan semua orang. Yang membedakan
 * sekarang KURSINYA: dewan yang memegang kursi di gelanggang tempat partai ini
 * dimainkan tetap boleh menghukum tanpa penugasan per partai, karena
 * penugasannya memang hidup di gelanggang.
 */
it('mengizinkan Dewan Wasit Juri menghukum lewat kursi gelanggangnya', function () {
    ($this->mulaiBabak)();

    ArenaOfficial::create([
        'arena_id' => $this->match->arena_id,
        'user_id' => $this->pengawas->id,
        'role' => 'dewan-juri',
    ]);

    $this->actingAs($this->pengawas)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

it('menolak hitungan teknik dari wasit yang tidak ditugaskan di partai ini', function () {
    ($this->mulaiBabak)();

    $this->actingAs($this->wasitGelanggangLain)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'hitungan' => 1,
        ])
        ->assertForbidden();
});

/*
 * Pengendali terikat gelanggang, bukan partai. Satu pengendali duduk di satu
 * gelanggang sepanjang hari, jadi penugasannya diberikan sekali per gelanggang
 * dan berlaku untuk seluruh partai yang dimainkan di sana.
 */
it('menolak pengendali gelanggang lain memulai babak', function () {
    [$gelanggangA, $gelanggangB] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangB->pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_TERJADWAL);
});

it('mengizinkan pengendali gelanggang partai memulai babak', function () {
    [$gelanggangA] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangA->pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak pengendali gelanggang lain mengakhiri partai', function () {
    [$gelanggangA, $gelanggangB] = ($this->siapkanGelanggang)();

    $this->actingAs($gelanggangA->pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($gelanggangB->pengendali)
        ->post(route('admin.turnamen.partai.akhiri', [$this->tournament, $this->match]), [
            'corner' => 'red', 'sebab' => 'mutlak',
        ])
        ->assertForbidden();

    expect($this->match->fresh()->winner_registration_id)->toBeNull();
});

it('tetap mengizinkan Ketua Pertandingan mengendalikan timer lintas gelanggang', function () {
    ($this->siapkanGelanggang)();

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

/*
 * Peran di sistem ini biasanya tunggal, tapi tidak dijamin begitu: satu akun
 * bisa memegang wasit sekaligus juri. Yang diperiksa harus "ditugaskan di
 * partai ini dalam salah satu kapasitas", bukan "ditugaskan dalam SETIAP
 * kapasitas yang perannya izinkan" -- kalau tidak, wasit yang kebetulan juga
 * berperan juri ikut tertolak menjatuhkan hukuman di partai yang memang
 * ditugaskan kepadanya.
 */
it('mengizinkan pemegang dua peran yang ditugaskan sebagai salah satunya', function () {
    $rangkap = User::factory()->create();
    $rangkap->syncRoles([peranSistem('wasit'), 'juri']);

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $rangkap->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);

    ($this->mulaiBabak)();

    $this->actingAs($rangkap)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertRedirect();

    expect($this->match->penalties()->count())->toBe(1);
});

it('tetap menolak pemegang dua peran yang tidak ditugaskan sama sekali', function () {
    $rangkap = User::factory()->create();
    $rangkap->syncRoles([peranSistem('wasit'), 'juri']);

    ($this->mulaiBabak)();

    $this->actingAs($rangkap)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'tingkat' => 'ringan',
        ])
        ->assertForbidden();

    expect($this->match->penalties()->count())->toBe(0);
});

/*
 * Bukti bahwa wewenangnya benar-benar DICABUT, bukan sekadar berpindah tangan.
 * Operator IT masih memegang gelanggang yang sama dan masih boleh membuka
 * panelnya sebagai papan tampilan -- yang hilang cuma kendali jalannya partai.
 */
it('menolak Operator IT memulai babak di gelanggangnya sendiri', function () {
    $this->actingAs($this->operator)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();

    expect($this->match->fresh()->status)->toBe(SilatMatch::STATUS_TERJADWAL);
});
