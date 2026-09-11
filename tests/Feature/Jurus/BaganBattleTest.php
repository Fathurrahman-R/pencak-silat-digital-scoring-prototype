<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusBattle;
use App\Models\JurusDeduction;
use App\Models\JurusEvent;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\PromosiPemenang;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Bagan\TahapBaganJurus;
use App\Support\Jurus\JurusScoreCalculator;
use App\Support\Jurus\PerbandinganBattle;
use App\Support\Jurus\PutuskanBattle;
use App\Support\Rekap\RekapMedali;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Naskah 2025 menyebutnya harfiah: "Pertandingan menggunakan Sistem Gugur"
 * (Pasal 12.1.b.1), dan dua kali lagi di bagian pemecah seri "karena format
 * Jurus sekarang menggunakan sistem gugur". Gelanggang Jurus punya Sudut Merah
 * dan Sudut Biru, dan penampilan pertama dilakukan sudut BIRU.
 *
 * Sistem ini dibangun dengan anggapan sebaliknya. Berkas ini menguji bentuk
 * yang benar, sekaligus menjaga nomor berformat lama tetap utuh.
 */

beforeEach(function () {
    // Dua uji terakhir memanggil rute admin, jadi peran dan resource key-nya
    // harus ada -- selebihnya murni domain dan tidak membutuhkannya.
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    /*
     * Nomornya SUDAH dibuat SusunMasterDataTurnamen -- diambil, bukan dibuat
     * ulang. Formatnya yang diubah, persis seperti yang dilakukan panitia lewat
     * menu Nomor Jurus.
     */
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
});

it('menyusun bagan gugur untuk nomor berformat battle', function () {
    ($this->daftarkan)(4);

    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    expect($bagan->size)->toBe(4)
        ->and($bagan->slots()->count())->toBe(4)
        // 4 peserta: dua battle penyisihan + satu final.
        ->and($bagan->battles()->count())->toBe(3);
});

it('menolak menyusun bagan untuk nomor berformat penampilan', function () {
    $this->nomor->update(['format' => FormatJurus::Penampilan]);
    ($this->daftarkan)(4);

    expect(fn () => $this->susun->untukNomor($this->nomor->fresh()))
        ->toThrow(RuntimeException::class, 'berformat penampilan');
});

it('meluluskan peserta yang lawannya bye', function () {
    ($this->daftarkan)(3);

    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $bye = $bagan->battles()->where('round', 1)->get()
        ->first(fn (JurusBattle $b) => $b->red_registration_id === null || $b->blue_registration_id === null);

    expect($bye->status)->toBe(JurusBattle::STATUS_SELESAI)
        ->and($bye->win_reason)->toBe('bye')
        ->and($bye->winner_registration_id)->not->toBeNull();

    // Pemenang bye sudah berdiri di final tanpa menunggu hari-H.
    $final = $bagan->battles()->where('round', 2)->sole();
    expect([$final->red_registration_id, $final->blue_registration_id])->toContain($bye->winner_registration_id);
});

/*
 * Naskah Pasal 12.1.b.2-5: tiap tahap memakai jurus dan durasi berbeda. Tahap
 * yang salah berarti waktu acuan yang salah dipakai pemecah seri.
 */
it('menurunkan tahap penampilan dari ronde bagan', function () {
    expect(TahapBaganJurus::untuk(1, 8))->toBe(TahapBaganJurus::PEREMPAT_FINAL)
        ->and(TahapBaganJurus::untuk(2, 8))->toBe(TahapBaganJurus::SEMIFINAL)
        ->and(TahapBaganJurus::untuk(3, 8))->toBe(TahapBaganJurus::FINAL)
        ->and(TahapBaganJurus::untuk(1, 32))->toBe(TahapBaganJurus::PENYISIHAN_1)
        ->and(TahapBaganJurus::untuk(2, 32))->toBe(TahapBaganJurus::PENYISIHAN_2);
});

/** Naskah Pasal 12.1.d.7: penampilan pertama dilakukan Pesilat sudut BIRU. */
it('menyiapkan dua penampilan per battle, biru lebih dulu', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $penampilan = $this->susun->siapkanPenampilan($battle);

    expect($penampilan)->toHaveCount(2)
        ->and($penampilan->first()->sudut)->toBe('biru')
        ->and($penampilan->last()->sudut)->toBe('merah')
        ->and($penampilan->first()->tahap)->toBe(TahapBaganJurus::SEMIFINAL);
});

it('menetapkan pemenang battle dari skor akhir tertinggi', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $penampilan = $this->susun->siapkanPenampilan($battle);
    $juri = collect(range(1, 4))->map(fn () => User::factory()->create());

    // Sudut merah dinilai lebih tinggi.
    foreach ($penampilan as $p) {
        $nilai = $p->sudut === 'merah' ? 9.80 : 9.50;

        foreach ($juri as $j) {
            JurusScore::create(['performance_id' => $p->id, 'judge_user_id' => $j->id, 'value' => $nilai]);
        }

        $p->update(['ratified_at' => now(), 'ratified_by' => $juri->first()->id]);
    }

    (new PutuskanBattle(new JurusScoreCalculator, new PromosiPemenang))($battle->fresh());

    expect($battle->fresh())
        ->status->toBe(JurusBattle::STATUS_SELESAI)
        ->win_reason->toBe('angka')
        ->winner_registration_id->toBe($battle->red_registration_id);
});

it('menaikkan pemenang battle ke ronde berikutnya', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->where('position', 1)->first();

    $penampilan = $this->susun->siapkanPenampilan($battle);
    $juri = collect(range(1, 4))->map(fn () => User::factory()->create());

    foreach ($penampilan as $p) {
        foreach ($juri as $j) {
            JurusScore::create([
                'performance_id' => $p->id, 'judge_user_id' => $j->id,
                'value' => $p->sudut === 'merah' ? 9.90 : 9.20,
            ]);
        }
        $p->update(['ratified_at' => now(), 'ratified_by' => $juri->first()->id]);
    }

    (new PutuskanBattle(new JurusScoreCalculator, new PromosiPemenang))($battle->fresh());

    $final = $bagan->battles()->where('round', 2)->sole();

    expect($final->red_registration_id)->toBe($battle->fresh()->winner_registration_id);
});

it('menolak menetapkan pemenang sebelum kedua penampilan disahkan', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->susun->siapkanPenampilan($battle);

    expect(fn () => (new PutuskanBattle(new JurusScoreCalculator, new PromosiPemenang))($battle->fresh()))
        ->toThrow(RuntimeException::class, 'disahkan lebih dulu');
});

/*
 * Naskah Pasal 12.1.b.6: "minimal diikuti 3 peserta, bila hanya terdapat 2
 * peserta tetap dipertandingkan tetapi perolehan medali tidak dihitung."
 */
it('tidak menghitung medali untuk nomor yang hanya diikuti dua peserta', function () {
    ($this->daftarkan)(2);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $battle = $bagan->battles()->where('round', 1)->sole();
    $battle->update([
        'winner_registration_id' => $battle->red_registration_id,
        'win_reason' => 'angka',
        'status' => JurusBattle::STATUS_SELESAI,
    ]);

    $medali = app(RekapMedali::class)->jurus($this->tournament->fresh());

    expect($medali)->toBeEmpty();
});

/** Nomor berformat penampilan tidak berubah sedikit pun. */
it('membiarkan nomor berformat penampilan memakai peringkat median', function () {
    $this->nomor->update(['format' => FormatJurus::Penampilan]);

    expect($this->nomor->fresh()->format->pakaiBagan())->toBeFalse()
        ->and(FormatJurus::Penampilan->label())->toBe('Penampilan (peringkat)');
});

/*
 * Komparasi nilai kedua sudut, yang dibaca sesudah battle selesai.
 *
 * "9.72 lawan 9.70" tidak menjelaskan apa pun sampai pembacanya tahu apakah
 * bedanya datang dari penilaian juri atau dari satu pengurangan 0.50 yang
 * dijatuhkan Pengawas -- dan pelatih yang mengangkat kartu protes menanyakan
 * persis itu.
 */
it('menyusun perbandingan nilai kedua sudut', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $penampilan = $this->susun->siapkanPenampilan($battle);
    $juri = collect(range(1, 4))->map(fn () => User::factory()->create());

    foreach ($penampilan as $p) {
        foreach ($juri as $j) {
            JurusScore::create([
                'performance_id' => $p->id, 'judge_user_id' => $j->id,
                'value' => $p->sudut === 'merah' ? 9.80 : 9.50,
            ]);
        }
    }

    // Satu pengurangan pengawas di sudut merah -- inilah yang harus terlihat
    // terpisah dari pengurangan juri.
    JurusDeduction::create([
        'performance_id' => $penampilan->firstWhere('sudut', 'merah')->id,
        'tier' => JurusDeduction::TIER_PENGAWAS,
        'alasan' => 'keluar gelanggang',
        'jumlah' => 0.50,
    ]);

    $banding = app(PerbandinganBattle::class)($battle->fresh());

    expect($banding['merah']['median'])->toBe(9.80)
        ->and($banding['merah']['pengurangan_pengawas'])->toBe(0.50)
        ->and($banding['merah']['pengurangan_juri'])->toBe(0.0)
        ->and($banding['merah']['akhir'])->toBe(9.30)
        ->and($banding['biru']['akhir'])->toBe(9.50)
        ->and($banding['selisih'])->toBe(0.20)
        ->and($banding['merah']['nilai_juri'])->toHaveCount(4);
});

/** Selisih hanya berarti kalau kedua sisi sudah punya angka. */
it('tidak menghitung selisih saat satu sudut belum punya penampilan', function () {
    ($this->daftarkan)(3);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $bye = $bagan->battles()->where('round', 1)->get()
        ->first(fn (JurusBattle $b) => $b->red_registration_id === null || $b->blue_registration_id === null);

    $this->susun->siapkanPenampilan($bye);

    $banding = app(PerbandinganBattle::class)($bye->fresh());

    expect($banding['selisih'])->toBeNull();
});

it('menampilkan halaman perbandingan battle', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();
    $this->susun->siapkanPenampilan($battle);

    $this->actingAs($admin)
        ->get(route('admin.turnamen.jurus.battle', [$this->tournament, $battle]))
        ->assertOk()
        ->assertSee('Sudut merah')
        ->assertSee('Sudut biru')
        ->assertSee('Pengurangan pengawas (0.50)')
        ->assertSee('perbandinganBattle', false);
});

it('menolak battle milik kejuaraan lain di alamat', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $lain = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    $this->actingAs($admin)
        ->get(route('admin.turnamen.jurus.battle', [$lain, $battle]))
        ->assertNotFound();
});

/*
 * Tanpa tautan ini panel perbandingan tidak punya pintu masuk sama sekali:
 * alamatnya menyebut id battle, dan tidak ada satu pun halaman yang
 * menyebutkannya.
 */
it('menautkan perbandingan battle dari halaman nomor jurus', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();

    $this->actingAs($admin)
        ->get(route('admin.turnamen.jurus.index', [$this->tournament, $this->nomor]))
        ->assertOk()
        ->assertSee('Perbandingan nilai')
        ->assertSee(route('admin.turnamen.jurus.battle', [$this->tournament, $battle]), false);
});

/** Nomor berformat penampilan tidak menggambar daftar battle sama sekali. */
it('tidak menggambar daftar battle untuk nomor berformat penampilan', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->nomor->update(['format' => FormatJurus::Penampilan]);

    $this->actingAs($admin)
        ->get(route('admin.turnamen.jurus.index', [$this->tournament, $this->nomor->fresh()]))
        ->assertOk()
        ->assertDontSee('Perbandingan nilai');
});

/*
 * Empat aksi di bawah adalah satu-satunya pintu masuk battle dari peramban.
 * Sebelum ada rutenya, SusunBaganJurus dan PutuskanBattle tidak punya satu pun
 * pemanggil: seluruh domain di atas hijau sementara fiturnya tidak bisa
 * disentuh siapa pun. Tes unit tidak pernah bisa memberi tahu bahwa sebuah
 * fitur tidak punya tombol.
 */

it('mengubah format nomor jurus lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->nomor->update(['format' => FormatJurus::Penampilan]);

    $this->actingAs($admin)
        ->post(route('admin.turnamen.jurus.format', [$this->tournament, $this->nomor]), [
            'format' => FormatJurus::Battle->value,
        ])
        ->assertRedirect();

    expect($this->nomor->fresh()->format)->toBe(FormatJurus::Battle);
});

/*
 * Penampilan berformat lama tidak punya battle maupun sudut. Membiarkan
 * formatnya berubah berarti peringkat median dan bagan gugur memperebutkan
 * baris yang sama.
 */
it('menolak mengubah format nomor yang sudah punya penampilan', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $this->susun->siapkanPenampilan($bagan->battles()->where('round', 1)->first());

    $this->actingAs($admin)
        ->from(route('admin.turnamen.jurus.nomor', $this->tournament))
        ->post(route('admin.turnamen.jurus.format', [$this->tournament, $this->nomor]), [
            'format' => FormatJurus::Penampilan->value,
        ])
        ->assertSessionHasErrors('format');

    expect($this->nomor->fresh()->format)->toBe(FormatJurus::Battle);
});

it('menyusun bagan dan penampilan ronde pertama lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);

    $this->actingAs($admin)
        ->post(route('admin.turnamen.jurus.susun-bagan', [$this->tournament, $this->nomor]), ['acak' => false])
        ->assertRedirect()
        ->assertSessionHas('success');

    $bagan = $this->nomor->fresh()->bagan;

    expect($bagan)->not->toBeNull()
        ->and($bagan->battles()->count())->toBe(3)
        // Dua battle ronde pertama, dua penampilan masing-masing.
        ->and($this->nomor->performances()->count())->toBe(4);
});

/** Rute menerjemahkan penolakan domain jadi galat validasi, bukan 500. */
it('menolak menyusun bagan nomor berformat penampilan lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    $this->nomor->update(['format' => FormatJurus::Penampilan]);
    ($this->daftarkan)(4);

    $this->actingAs($admin)
        ->from(route('admin.turnamen.jurus.index', [$this->tournament, $this->nomor]))
        ->post(route('admin.turnamen.jurus.susun-bagan', [$this->tournament, $this->nomor]), ['acak' => false])
        ->assertSessionHasErrors('aksi');
});

it('menyiapkan penampilan ronde lanjutan lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    // Final baru punya dua nama setelah kedua semifinalnya selesai; di sini
    // keadaan itu dipasang langsung supaya yang diuji tinggal rutenya.
    $final = $bagan->battles()->where('round', 2)->sole();
    $isi = $bagan->slots()->whereNotNull('registration_id')->orderBy('position')->get();
    $final->update([
        'red_registration_id' => $isi[0]->registration_id,
        'blue_registration_id' => $isi[2]->registration_id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.turnamen.jurus.battle.penampilan', [$this->tournament, $final]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($final->fresh()->performances()->count())->toBe(2);
});

it('menolak menyiapkan penampilan battle yang sudutnya belum lengkap', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $final = $bagan->battles()->where('round', 2)->sole();

    $this->actingAs($admin)
        ->from(route('admin.turnamen.jurus.index', [$this->tournament, $this->nomor]))
        ->post(route('admin.turnamen.jurus.battle.penampilan', [$this->tournament, $final]))
        ->assertSessionHasErrors('battle');

    expect($final->fresh()->performances()->count())->toBe(0);
});

it('menetapkan pemenang battle lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->where('position', 1)->first();

    $penampilan = $this->susun->siapkanPenampilan($battle);
    $juri = collect(range(1, 4))->map(fn () => User::factory()->create());

    foreach ($penampilan as $p) {
        foreach ($juri as $j) {
            JurusScore::create([
                'performance_id' => $p->id, 'judge_user_id' => $j->id,
                'value' => $p->sudut === 'merah' ? 9.85 : 9.40,
            ]);
        }
        $p->update(['ratified_at' => now(), 'ratified_by' => $juri->first()->id]);
    }

    $this->actingAs($admin)
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($battle->fresh()->winner_registration_id)->toBe($battle->red_registration_id);
});

it('menolak menetapkan pemenang sebelum pengesahan lewat rute admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles([config('resources.super_admin_role')]);

    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);
    $battle = $bagan->battles()->where('round', 1)->first();
    $this->susun->siapkanPenampilan($battle);

    $this->actingAs($admin)
        ->from(route('admin.turnamen.jurus.battle', [$this->tournament, $battle]))
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]))
        ->assertSessionHasErrors('aksi');

    expect($battle->fresh()->winner_registration_id)->toBeNull();
});
