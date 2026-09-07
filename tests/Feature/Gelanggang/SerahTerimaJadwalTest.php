<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\SerahJadwal;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\SerahTerimaJadwal;
use App\Support\Sinkron\Kepemilikan;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Memindahkan jadwal antar gelanggang, dari panel kendali, tanpa node global.
 *
 * Yang dijaga berkas ini bukan tombolnya melainkan aturan kepemilikannya.
 * `arena_id` adalah kolom yang menentukan node mana yang berhak menulis satu
 * baris; memindahkannya berarti memindahkan hak tulis, dan sistem ini tidak
 * punya resolusi konflik yang bisa menambal kalau dua node sama-sama merasa
 * berhak.
 *
 * Jalan keluarnya dua setengah-catatan: pelepas menulis penawarannya, penerima
 * menulis adopsinya, dan barulah `arena_id` berpindah. Tidak ada satu baris
 * pun yang pernah ditulis dua node.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'A',
        'sinkron.node' => 'gelanggang-a',
        'sinkron.token' => 'rahasia-uji',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arenaA = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A', 'code' => 'A']);
    $this->arenaB = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang B', 'code' => 'B']);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bagan = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $posisi = 0;
    $this->buatPartai = function (Arena $arena) use ($bagan, $daftar, &$posisi) {
        return SilatMatch::create([
            'bracket_id' => $bagan->id, 'round' => 1, 'position' => ++$posisi,
            'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
            'arena_id' => $arena->id, 'order_in_arena' => $posisi,
        ]);
    };

    $this->partai = ($this->buatPartai)($this->arenaA);

    $this->pengendaliA = User::factory()->create();
    $this->pengendaliA->syncRoles(['pengendali-gelanggang']);
    $this->arenaA->pengendali()->attach($this->pengendaliA->id);

    $this->pengendaliB = User::factory()->create();
    $this->pengendaliB->syncRoles(['pengendali-gelanggang']);
    $this->arenaB->pengendali()->attach($this->pengendaliB->id);

    $this->lepas = fn (array $ubah = []) => $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.lepas', [$this->tournament, $this->arenaA]), [
            'ke_arena_id' => $this->arenaB->id,
            'jenis' => SerahJadwal::TANDING,
            'baris_id' => $this->partai->id,
            ...$ubah,
        ]);
});

it('melepas partai ke gelanggang lain dari panel kendali', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    expect($serah->arena_id)->toBe($this->arenaA->id)
        ->and($serah->ke_arena_id)->toBe($this->arenaB->id)
        ->and($serah->sudahDiambil())->toBeFalse()
        // Belum berpindah pemilik: itu baru terjadi saat diambil.
        ->and($this->partai->fresh()->arena_id)->toBe($this->arenaA->id);
});

/*
 * Selama menggantung, baris itu tidak boleh ada di layar mana pun. Yang
 * menontonnya di gelanggang asal akan mengira partainya masih dimainkan di
 * sana, padahal pengendali sudah melepasnya.
 */
it('berhenti menayangkan partai yang dilepas, seketika', function () {
    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaA]), [
            'match_id' => $this->partai->id,
        ])->assertSessionHasNoErrors();

    expect($this->arenaA->fresh()->active_match_id)->toBe($this->partai->id);

    ($this->lepas)()->assertSessionHasNoErrors();

    expect($this->arenaA->fresh()->active_match_id)->toBeNull();
});

/*
 * Dua tangan. Penerima harus mengambil lebih dulu -- sebelum itu partai masih
 * milik gelanggang asal, dan penjagaan penayangan menolaknya dengan alasan
 * yang benar.
 */
it('menolak gelanggang tujuan menayangkan sebelum mengambil', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaB]), [
            'match_id' => $this->partai->id,
        ])
        ->assertSessionHasErrors('aksi');

    expect($this->arenaB->fresh()->active_match_id)->toBeNull();
});

it('memindahkan partai begitu gelanggang tujuan mengambilnya', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.ambil', [$this->tournament, $this->arenaB, $serah]))
        ->assertSessionHasNoErrors();

    $partai = $this->partai->fresh();

    expect($partai->arena_id)->toBe($this->arenaB->id)
        // Urutan tayang dikosongkan: nomor urut milik antrean gelanggang ASAL,
        // dan membawanya serta menyisipkan partai di tengah antrean orang lain.
        ->and($partai->order_in_arena)->toBeNull()
        ->and($serah->fresh()->sudahDiambil())->toBeTrue();

    // Dan sekarang gelanggang tujuan boleh menayangkannya.
    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaB]), [
            'match_id' => $partai->id,
        ])
        ->assertSessionHasNoErrors();

    expect($this->arenaB->fresh()->active_match_id)->toBe($partai->id);
});

it('membatalkan penawaran selama belum diambil', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.batal', [$this->tournament, $this->arenaA, $serah]))
        ->assertSessionHasNoErrors();

    expect($serah->fresh()->dibatalkan())->toBeTrue()
        ->and($this->partai->fresh()->arena_id)->toBe($this->arenaA->id);
});

/*
 * Sesudah diambil, barisnya sudah bukan milik pelepas -- membatalkannya berarti
 * menulis baris orang lain. Jalan kembalinya pemindahan baru ke arah
 * sebaliknya, dan itu keputusan pengendali tujuan.
 */
it('menolak pembatalan sesudah penawaran diambil', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.ambil', [$this->tournament, $this->arenaB, $serah]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.batal', [$this->tournament, $this->arenaA, $serah]))
        ->assertSessionHasErrors('aksi');

    expect($serah->fresh()->dibatalkan())->toBeFalse();
});

it('menolak melepas partai milik gelanggang lain', function () {
    $milikB = ($this->buatPartai)($this->arenaB);

    ($this->lepas)(['baris_id' => $milikB->id])->assertSessionHasErrors('aksi');

    expect(SerahJadwal::count())->toBe(0);
});

it('menolak melepas ke gelanggang yang sama', function () {
    ($this->lepas)(['ke_arena_id' => $this->arenaA->id])->assertSessionHasErrors('aksi');

    expect(SerahJadwal::count())->toBe(0);
});

/*
 * Inti aturan satu penulis, dan alasan seluruh rancangan dua setengah-catatan.
 *
 * Tiap setengah dimiliki node yang menulisnya. Node pemegang Gelanggang A
 * mengirimkan penawarannya dan menolak adopsi milik B; node pemegang B
 * sebaliknya. Tidak ada satu baris pun yang keduanya klaim.
 */
it('memberi tiap setengah catatan pemilik yang berbeda', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.ambil', [$this->tournament, $this->arenaB, $serah]));

    $barisSerah = (array) DB::table('serah_jadwal')->where('id', $serah->id)->first();
    $barisAdopsi = (array) DB::table('adopsi_jadwal')->where('serah_id', $serah->id)->first();

    // Node yang memegang Gelanggang A.
    config(['sinkron.arena' => 'A']);
    $kepemilikanA = app(Kepemilikan::class);

    expect($kepemilikanA->milikNodeIni('serah_jadwal', $barisSerah))->toBeTrue()
        ->and($kepemilikanA->milikNodeIni('adopsi_jadwal', $barisAdopsi))->toBeFalse();

    // Node yang memegang Gelanggang B.
    config(['sinkron.arena' => 'B']);
    $kepemilikanB = app(Kepemilikan::class);

    expect($kepemilikanB->milikNodeIni('serah_jadwal', $barisSerah))->toBeFalse()
        ->and($kepemilikanB->milikNodeIni('adopsi_jadwal', $barisAdopsi))->toBeTrue();
});

it('menampilkan penawaran yang menggantung di kedua sisi', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();
    $terima = app(SerahTerimaJadwal::class);

    expect($terima->menungguDiambil($this->arenaA)->pluck('id')->all())->toBe([$serah->id])
        ->and($terima->ditawarkanKe($this->arenaB)->pluck('id')->all())->toBe([$serah->id])
        // Bukan urusan gelanggang yang tidak terlibat.
        ->and($terima->ditawarkanKe($this->arenaA)->all())->toBe([]);
});

/*
 * Pesan penolakan menyebut gelanggang ASALNYA.
 *
 * Pengendali tujuan melihat partai itu di daftar "ditawarkan dari gelanggang
 * lain" lalu menekan Tayangkan tanpa menekan Ambil lebih dulu. Menjawabnya
 * "tidak dijadwalkan di sini" menyuruhnya mencari kesalahan yang tidak ada,
 * sementara yang kurang cuma satu tombol di layar yang sama.
 */
it('menyebut gelanggang asal saat tujuan mencoba menayangkan sebelum mengambil', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaB]), [
            'match_id' => $this->partai->id,
        ])
        ->assertSessionHasErrors(['aksi' => 'Partai ini masih ditawarkan dari Gelanggang A. Tekan Ambil lebih dulu, baru bisa ditayangkan di sini.']);
});

/*
 * Pengendali tidak keluar ke layar lain: daftar gelanggang tujuan, penawaran
 * yang menggantung, dan penawaran yang masuk semuanya ikut di muatan panel
 * kendali.
 *
 * Sengaja TIDAK di endpoint `state` yang ditarik tiap panel: daftarnya berubah
 * beberapa kali sehari, sementara `state` ditarik tiap tekanan tombol juri.
 */
it('mengirim daftar serah-terima di muatan panel kendali', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliA)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arenaA]))
        ->assertOk()
        ->assertJsonPath('panel.serah.menunggu.0.tujuan', 'Gelanggang B')
        ->assertJsonPath('panel.serah.gelanggang.0.nama', 'Gelanggang B')
        ->assertJsonCount(0, 'panel.serah.ditawarkan');

    $this->actingAs($this->pengendaliB)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arenaB]))
        ->assertOk()
        ->assertJsonPath('panel.serah.ditawarkan.0.asal', 'Gelanggang A')
        ->assertJsonCount(0, 'panel.serah.menunggu');
});

/* Juri tidak boleh ikut membaca jadwal lewat endpoint penilaian. */
it('tidak mengirim daftar serah-terima kepada yang bukan pengendali', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arenaA]))
        ->assertOk()
        ->assertJsonMissingPath('panel.serah');
});

/*
 * Ditemukan lewat blackbox testing di peramban, bukan lewat uji ini.
 *
 * Melepas mengosongkan pointer kalau partainya sedang tayang -- tapi tidak ada
 * yang mencegah pengendali menunjuknya LAGI semenit kemudian. Gelanggang A
 * menayangkan partai yang sudah ia tawarkan ke B, tanpa satu pun pesan, dan
 * dua gelanggang sama-sama menganggapnya miliknya.
 */
it('menolak pelepas menayangkan ulang partai yang sedang ditawarkan', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaA]), [
            'match_id' => $this->partai->id,
        ])
        ->assertSessionHasErrors('aksi');

    expect($this->arenaA->fresh()->active_match_id)->toBeNull();
});

/* Sesudah penawarannya ditarik kembali, ia boleh ditayangkan lagi. */
it('mengizinkan menayangkan lagi sesudah penawaran dibatalkan', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.batal', [$this->tournament, $this->arenaA, $serah]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliA)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arenaA]), [
            'match_id' => $this->partai->id,
        ])
        ->assertSessionHasNoErrors();

    expect($this->arenaA->fresh()->active_match_id)->toBe($this->partai->id);
});

/*
 * Temuan kedua dari blackbox testing, dan yang paling merugikan: penawaran
 * terserap, lalu partainya lenyap.
 *
 * Adopsi sengaja tidak membawa nomor urut gelanggang asal. `antrean()` menaruh
 * partai tanpa nomor di paling belakang lalu memotong dua puluh -- dan di
 * gelanggang dengan 281 partai terjadwal, partai yang baru diambil tidak
 * pernah terlihat sama sekali. Pengendali menekan Ambil, penawarannya hilang
 * dari layar, dan tidak ada satu pun tempat partai itu muncul.
 */
it('menampilkan partai yang baru diambil di daftar "baru masuk"', function () {
    ($this->lepas)()->assertSessionHasNoErrors();

    $serah = SerahJadwal::firstOrFail();

    $this->actingAs($this->pengendaliB)
        ->post(route('admin.turnamen.gelanggang.panel.lepas.ambil', [$this->tournament, $this->arenaB, $serah]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->pengendaliB)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arenaB]))
        ->assertOk()
        ->assertJsonPath('panel.serah.baruMasuk.0.id', $this->partai->id);
});
