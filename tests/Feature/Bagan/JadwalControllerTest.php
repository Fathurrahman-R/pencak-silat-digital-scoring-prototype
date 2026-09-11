<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
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
    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 1']);

    $this->buatPartai = function (string $kodeKelas = 'C'): SilatMatch {
        $kelas = $this->tournament->weightClasses()
            ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', $kodeKelas)->firstOrFail();

        $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

        $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $regMerah->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

        $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
        $regBiru->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
            'red_registration_id' => $regMerah->id, 'blue_registration_id' => $regBiru->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
        ]);
    };
});

it('menampilkan gelanggang beserta partai yang belum dijadwalkan', function () {
    ($this->buatPartai)();

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.jadwal.index', $this->tournament))
        ->assertOk()
        ->assertSee('Gelanggang 1')
        ->assertSee('Belum dijadwalkan');
});

/*
 * Jadwal dibawa ke gelanggang sebagai kertas: panitia meja gelanggang tidak
 * selalu punya layar, dan daftar di tangan tidak ikut berubah saat seseorang
 * menggeser urutan di panel.
 */
it('mencetak jadwal sebagai PDF', function () {
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)->post(
        route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]),
        ['arena_id' => $this->arena->id],
    );

    $respons = $this->actingAs($this->admin)
        ->get(route('admin.turnamen.jadwal.cetak', $this->tournament))
        ->assertOk();

    expect($respons->headers->get('content-type'))->toContain('application/pdf')
        ->and($respons->getContent())->toStartWith('%PDF');
});

it('menjadwalkan partai lewat form', function () {
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
            'arena_id' => $this->arena->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($partai->fresh()->arena_id)->toBe($this->arena->id);
});

it('menolak menjadwalkan ke gelanggang kejuaraan lain', function () {
    $partai = ($this->buatPartai)();
    $arenaLain = Arena::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
            'arena_id' => $arenaLain->id,
        ])
        ->assertSessionHasErrors('arena_id');

    expect($partai->fresh()->arena_id)->toBeNull();
});

it('melepas jadwal partai lewat form', function () {
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)->post(route('admin.turnamen.jadwal.tetapkan', [$this->tournament, $partai]), [
        'arena_id' => $this->arena->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partai]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($partai->fresh()->arena_id)->toBeNull();
});

it('menolak partai yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $kelasLain = $turnamenLain->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracketLain = Bracket::create(['weight_class_id' => $kelasLain->id, 'size' => 2]);
    $partaiLain = SilatMatch::create([
        'bracket_id' => $bracketLain->id, 'round' => 1, 'position' => 1,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.jadwal.lepas', [$this->tournament, $partaiLain]))
        ->assertNotFound();
});

/*
 * --------------------------------------------------------------------
 * Memindahkan urutan, dan kelengkapan aparat
 * --------------------------------------------------------------------
 */

it('memindahkan partai ke urutan tujuan dalam satu tindakan', function () {
    /*
     * Jalur urutkan() menukar dengan tetangga sebelah: memindahkan partai dari
     * urutan 4 ke urutan 1 lewat jalur itu berarti tiga permintaan dan tiga
     * pemuatan ulang halaman. Panitia yang menyusun jadwal pagi hari
     * melakukannya berkali-kali untuk gelanggang berisi puluhan partai.
     */
    // Empat partai dalam SATU bracket: satu kelas hanya boleh punya satu bagan.
    $partai = collect(['C', 'B', 'A', 'D'])->map(function (string $kode) {
        $m = ($this->buatPartai)($kode);
        $this->actingAs($this->admin)->post(
            "/admin/turnamen/{$this->tournament->id}/jadwal/{$m->id}/tetapkan",
            ['arena_id' => $this->arena->id],
        );

        return $m->refresh();
    });

    $terakhir = $partai->last();
    expect($terakhir->order_in_arena)->toBe(4);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/jadwal/{$terakhir->id}/pindahkan", ['urutan' => 1])
        ->assertRedirect();

    $urut = SilatMatch::where('arena_id', $this->arena->id)
        ->orderBy('order_in_arena')->pluck('id')->all();

    expect($urut[0])->toBe($terakhir->id)
        // Sisanya bergeser rapat, tanpa nomor ganda maupun bolong.
        ->and(SilatMatch::where('arena_id', $this->arena->id)->orderBy('order_in_arena')
            ->pluck('order_in_arena')->all())->toBe([1, 2, 3, 4]);
});

it('merapatkan nomor urut yang bolong saat memindahkan', function () {
    /*
     * Nomor urut jadi bolong tiap kali satu partai dilepas dari jadwal.
     * Pemindahan menulis ulang seluruh urutan, bukan cuma yang bergeser —
     * menghitung mana saja yang berubah menghemat beberapa UPDATE dan membuka
     * celah nomor ganda.
     */
    $a = ($this->buatPartai)();
    $b = ($this->buatPartai)('B');

    foreach ([$a, $b] as $m) {
        $this->actingAs($this->admin)->post(
            "/admin/turnamen/{$this->tournament->id}/jadwal/{$m->id}/tetapkan",
            ['arena_id' => $this->arena->id],
        );
    }

    // Bolong dibuat langsung, meniru sisa penghapusan.
    SilatMatch::whereKey($b->id)->update(['order_in_arena' => 9]);

    $this->actingAs($this->admin)
        ->post("/admin/turnamen/{$this->tournament->id}/jadwal/{$b->id}/pindahkan", ['urutan' => 1])
        ->assertRedirect();

    expect(SilatMatch::where('arena_id', $this->arena->id)->orderBy('order_in_arena')
        ->pluck('order_in_arena')->all())->toBe([1, 2]);
});

it('menyatakan partai yang aparatnya belum lengkap', function () {
    /*
     * Partai yang jurinya belum lengkap sebelumnya tampil sama persis dengan
     * yang siap. Panel juri tidak akan menerima nilai sampai ketiganya
     * ditugaskan, dan itu baru ketahuan saat partai dimulai di depan penonton.
     */
    $partai = ($this->buatPartai)();

    $this->actingAs($this->admin)->post(
        "/admin/turnamen/{$this->tournament->id}/jadwal/{$partai->id}/tetapkan",
        ['arena_id' => $this->arena->id],
    );

    $halaman = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/jadwal")
        ->assertOk();

    // Belum ada satu pun aparat: wasit belum ada.
    $halaman->assertSee('Wasit belum ada')->assertSee('Lengkapi aparat gelanggang');

    /*
     * Tiap partai punya baris kelengkapannya, termasuk yang belum pernah
     * ditayangkan -- dan untuk yang belum, angkanya dibaca dari KURSI
     * GELANGGANG.
     *
     * Sejak penugasan aparat pindah ke gelanggang (September 2026),
     * `match_officials` baru terisi saat pengendali menunjuk partainya.
     * Membaca tabel itu saja membuat seluruh jadwal pagi hari tertulis "Wasit
     * belum ada" padahal tiap matras sudah lengkap -- peringatan yang salah
     * setiap hari akan berhenti dibaca justru sebelum hari ia benar.
     */
    $aparat = $halaman->viewData('aparat');
    expect($aparat->get($partai->id))->toBe(['wasit' => false, 'juri' => 0]);
});
