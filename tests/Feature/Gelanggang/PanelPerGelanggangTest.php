<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\KesiapanHulu;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Scoring\BabakSusulan;
use App\Support\Scoring\MatchTimer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Panel yang mengikuti gelanggang, bukan satu partai.
 *
 * Yang diuji di sini adalah apa yang membuat petugas berhenti mengurus
 * navigasi: alamatnya tidak menyebut partai sama sekali, isinya mengikuti
 * pointer, dan gelanggang yang belum dipilihkan partai tetap membalas halaman
 * yang bisa dibaca.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->arena = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);
    $kontingen = Contingent::factory()->for($this->tournament)->create();

    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    $daftar = fn () => tap(
        Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]),
        fn ($r) => $r->athletes()->attach(Athlete::factory()->for($kontingen)->create()),
    );

    $this->buatPartai = function (int $posisi) use ($bracket, $daftar) {
        return SilatMatch::create([
            'bracket_id' => $bracket->id, 'round' => 1, 'position' => $posisi,
            'red_registration_id' => $daftar()->id, 'blue_registration_id' => $daftar()->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
            'arena_id' => $this->arena->id, 'order_in_arena' => $posisi,
        ]);
    };

    $this->match = ($this->buatPartai)(1);

    $this->pengendali = User::factory()->create();
    $this->pengendali->syncRoles(['pengendali-gelanggang']);
    $this->arena->pengendali()->attach($this->pengendali->id);

    $this->pointer = new PointerTayang(new MatchTimer, app(KesiapanHulu::class));
});

it('membuka panel kendali gelanggang', function () {
    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Antrean gelanggang')
        ->assertSee('Gelanggang A');
});

/*
 * Inti perubahannya: alamat panel juri tidak menyebut partai sama sekali, jadi
 * ia tidak pernah basi saat jadwal berganti.
 */
it('membuka panel juri lewat alamat gelanggang, tanpa menyebut partai', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('partaiPanel', false);
});

/*
 * Gelanggang yang belum dipilihkan partai adalah keadaan normal -- pagi hari
 * sebelum partai pertama, dan jeda antar kelas. Yang membukanya tidak sedang
 * salah alamat.
 */
it('menampilkan layar tunggu saat gelanggang belum punya partai aktif', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Menunggu pengendali memilih partai');
});

/*
 * Panel kendali dikecualikan dari layar tunggu, dan pengecualian itu yang
 * membuat gelanggang bisa dimulai sama sekali.
 *
 * Ia satu-satunya panel yang tugasnya MEMILIH partai. Sempat ia ikut
 * dialihkan ke layar tunggu bersama panel petugas lain, dan hasilnya
 * gelanggang kosong -- keadaan tiap gelanggang setiap pagi -- tidak punya
 * jalan keluar: layarnya menyuruh pengendali memilih partai sambil
 * menyembunyikan satu-satunya tombol untuk memilihnya. Jalan keluarnya saat
 * itu cuma menyunting basis data.
 */
it('tetap menampilkan antrean di panel kendali walau gelanggang masih kosong', function () {
    expect($this->arena->fresh()->active_match_id)->toBeNull();

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Antrean gelanggang')
        ->assertDontSee('Menunggu pengendali memilih partai')
        // Tombol pemilih partai ikut terkirim. Tanpanya halaman ini cuma
        // daftar bacaan, dan gelanggang tetap tidak bisa dimulai.
        ->assertSee('Tayangkan')
        ->assertSee('pilihPartai(partai.id)', false);
});

it('memindahkan partai aktif lewat panel kendali', function () {
    $berikutnya = ($this->buatPartai)(2);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => $berikutnya->id,
        ])
        ->assertSessionHasNoErrors();

    expect($this->arena->fresh()->active_match_id)->toBe($berikutnya->id);
});

it('menolak pengendali gelanggang lain memindahkan jadwal', function () {
    $lain = User::factory()->create();
    $lain->syncRoles(['pengendali-gelanggang']);
    Arena::factory()->for($this->tournament)->create()->pengendali()->attach($lain->id);

    $this->actingAs($lain)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => $this->match->id,
        ])
        ->assertForbidden();

    expect($this->arena->fresh()->active_match_id)->toBeNull();
});

it('menerjemahkan penolakan domain jadi galat validasi, bukan 500', function () {
    $berikutnya = ($this->buatPartai)(2);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    (new MatchTimer)->mulaiBabak($this->match, 1);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => $berikutnya->id,
        ])
        ->assertSessionHasErrors('aksi');

    expect($this->arena->fresh()->active_match_id)->toBe($this->match->id);
});

it('mengirim antrean gelanggang di payload state untuk pengendali', function () {
    ($this->buatPartai)(2);
    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('panel.mode', 'gelanggang')
        ->assertJsonPath('panel.antrean.0.aktif', true)
        ->assertJsonCount(2, 'panel.antrean');
});

/*
 * Panel juri menarik endpoint yang sama. Mengirim jadwal seluruh gelanggang
 * lewat endpoint penilaian berarti izin `penilaian.create` diam-diam ikut
 * memberi akses baca jadwal -- kebocoran yang tidak pernah dinyatakan.
 */
it('tidak mengirim antrean gelanggang kepada juri', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($juri)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonMissingPath('panel.antrean');
});

it('membalas match null saat gelanggang kosong, bukan 404', function () {
    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('match', null)
        ->assertJsonPath('panel.mode', 'gelanggang');
});

it('menolak gelanggang milik kejuaraan lain di alamat', function () {
    $lain = Tournament::factory()->create(['starts_on' => '2026-09-01']);

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$lain, $this->arena]))
        ->assertNotFound();
});

/*
 * Rute per-partai TIDAK dihapus: dewan wasit juri tetap harus bisa membuka
 * partai lama yang sudah selesai untuk ditinjau dan disahkan.
 */
it('mempertahankan panel per-partai untuk peninjauan partai lama', function () {
    $dewan = User::factory()->create();
    $dewan->syncRoles(['pengawas-wasit-juri']);

    $this->actingAs($dewan)
        ->get(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->assertOk();
});

/*
 * Tautan per-partai yang sudah beredar -- di pesan WhatsApp, di riwayat
 * peramban HP juri -- tidak pecah, tapi juga tidak boleh mendaratkan petugas
 * di partai yang sudah lewat.
 */
it('mengantar alamat juri per-partai ke panel gelanggangnya', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $juri->id,
        'role' => MatchOfficial::ROLE_JURI, 'number' => 1,
    ]);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertRedirect(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]));
});

/*
 * Papan tampilan mengikuti gelanggang, bukan satu partai.
 *
 * Ia dipasang di layar besar di pinggir matras dan tidak pernah disentuh
 * seharian; begitu pengendali memindahkan jadwal, papan yang masih memajang
 * partai sebelumnya adalah papan yang MENYESATKAN penonton dan official --
 * dan tidak ada satu orang pun yang berdiri di dekatnya untuk memuat ulang.
 * Alamat per-partai karena itu diantar ke alamat gelanggangnya, sama seperti
 * panel juri dan wasit.
 */
it('mengantar alamat papan per-partai ke panel gelanggangnya', function () {
    $operator = User::factory()->create();
    $operator->syncRoles(['operator-it']);

    /*
     * Operator terikat GELANGGANG, bukan partai -- baris match_officials saja
     * tidak cukup, dan penjagaannya menolak dengan 403 sebelum pengalihan
     * sempat terjadi. Penugasan gelanggangnya yang membuat kasus ini benar-
     * benar menguji pengalihan, bukan penolakan.
     */
    $this->arena->operators()->attach($operator->id);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $operator->id,
        'role' => MatchOfficial::ROLE_WASIT, 'number' => null,
    ]);

    $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertRedirect(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]));
});

/*
 * Dewan wasit juri TIDAK dialihkan: tugasnya justru meninjau partai tertentu,
 * termasuk yang sudah selesai, lalu mencetak berita acaranya.
 */
it('tidak mengalihkan panel dewan wasit juri', function () {
    $dewan = User::factory()->create();
    $dewan->syncRoles(['pengawas-wasit-juri']);

    $this->actingAs($dewan)
        ->get(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->assertOk();
});

it('menyajikan manifest PWA per gelanggang, per peran', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, 'juri']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertJsonPath('display', 'fullscreen');
});

it('menolak peran yang tidak punya panel di manifest', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, 'bendahara']))
        ->assertNotFound();
});

/*
 * Membuka babak lama melonggarkan penjagaan babak yang sudah ditutup --
 * wewenang terberat di gelanggang.
 */
it('membuka dan menutup babak susulan lewat panel kendali', function () {
    $timer = new MatchTimer;

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    $timer->mulaiBabak($this->match, 1);
    $timer->selesaikanBabak($this->match->fresh()->babakAktif());
    $timer->mulaiBabak($this->match->fresh(), 2);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.gelanggang.panel.babak-susulan.buka', [$this->tournament, $this->arena]), ['babak' => 1])
        ->assertSessionHasNoErrors();

    expect($this->match->fresh())
        ->susulan_round->toBe(1)
        ->current_round->toBe(2);

    $this->actingAs($this->pengendali)
        ->delete(route('admin.turnamen.gelanggang.panel.babak-susulan.tutup', [$this->tournament, $this->arena]))
        ->assertSessionHasNoErrors();

    expect($this->match->fresh()->susulan_round)->toBeNull();
});

it('menolak wasit membuka babak susulan', function () {
    $wasit = User::factory()->create();
    $wasit->syncRoles(['wasit']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($wasit)
        ->post(route('admin.turnamen.gelanggang.panel.babak-susulan.buka', [$this->tournament, $this->arena]), ['babak' => 1])
        ->assertForbidden();
});

it('mengirim blok susulan di payload state', function () {
    $timer = new MatchTimer;

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    $timer->mulaiBabak($this->match, 1);
    $timer->selesaikanBabak($this->match->fresh()->babakAktif());
    $timer->mulaiBabak($this->match->fresh(), 2);

    (new BabakSusulan($timer))->buka($this->match->fresh(), 1, $this->pengendali);

    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('susulan.round', 1)
        ->assertJsonPath('rounds.0.susulan', true)
        ->assertJsonPath('rounds.1.susulan', false);
});

/*
 * Wasit Komisi Protes tidak punya panel lain. Selama panel keberatan hanya
 * hidup di alamat per-partai, satu-satunya peran yang pekerjaannya ADA di
 * layar itu justru harus mencari alamatnya setiap kali partai berganti.
 */
it('membuka panel komisi protes lewat alamat gelanggang', function () {
    $komisi = User::factory()->create();
    $komisi->syncRoles(['wasit-komisi-protes']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($komisi)
        ->get(route('admin.turnamen.gelanggang.panel.komisi-protes', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Protes VAR')
        ->assertSee('partaiPanel', false);
});

/*
 * Ketua Pertandingan hadir DI gelanggang -- pesilat memberi hormat kepadanya,
 * ia memanggil Wasit dengan bel saat pesilat cedera. Panel gelanggangnya
 * mengikuti partai aktif seperti peran lain; ringkasan lintas gelanggang tetap
 * ada sebagai halaman terpisah untuk tugas kejuaraannya.
 */
it('membuka panel gelanggang ketua pertandingan tanpa membuang ringkasan lintas gelanggang', function () {
    $ketua = User::factory()->create();
    $ketua->syncRoles(['ketua-pertandingan']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($ketua)
        ->get(route('admin.turnamen.gelanggang.panel.ketua', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Ketua Pertandingan')
        ->assertSee('Protes VAR')
        ->assertSee(route('admin.turnamen.ketua-pertandingan.index', $this->tournament), false);

    $this->actingAs($ketua)
        ->get(route('admin.turnamen.ketua-pertandingan.index', $this->tournament))
        ->assertOk();
});

/*
 * Pasal 15 ayat 3 huruf d: protes VAR diputus Wasit Komisi Protes BERSAMA
 * Pengawas/Dewan Wasit Juri dan Wasit. Ketiganya harus melihat kartu yang sama
 * tanpa meninggalkan panelnya, karena tenggat lima menit berjalan selama
 * perpindahan halaman.
 */
it('menampilkan blok keberatan di panel dewan wasit juri', function () {
    $dewan = User::factory()->create();
    $dewan->syncRoles(['pengawas-wasit-juri']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    $this->actingAs($dewan)
        ->get(route('admin.turnamen.gelanggang.panel.dewan-juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Protes VAR');
});

it('menyiapkan blok keberatan di panel wasit untuk protes yang sedang berjalan', function () {
    $wasit = User::factory()->create();
    $wasit->syncRoles(['wasit']);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);

    MatchOfficial::create([
        'match_id' => $this->match->id, 'user_id' => $wasit->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Protes VAR')
        // Kartunya MENGGANTIKAN tangga hukuman, tidak menumpang di bawahnya:
        // panel ini dirancang untuk 844x390 dan sudah penuh.
        ->assertSee('protesBerjalan', false);
});

/*
 * Papan gelanggang sempat memakai manifest JURI.
 *
 * `peranDariView()` tidak mengenali viewnya, jadi ia jatuh ke `default` --
 * dan ikon yang dipasang di layar utama laptop gelanggang menyebut dirinya
 * Panel Juri. Persis kekeliruan yang manifest per-peran ini dibuat untuk
 * mencegah.
 */
it('menyajikan manifest papan gelanggang dengan namanya sendiri', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, 'papan']))
        ->assertOk()
        ->assertJsonPath('start_url', route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertJsonPath('short_name', 'Papan '.$this->arena->code)
        ->assertJsonPath('name', 'Papan Gelanggang — '.$this->arena->name);
});

it('menyajikan manifest PWA untuk komisi protes dan ketua pertandingan', function () {
    foreach (['komisi-protes' => 'Protes', 'ketua' => 'Ketua'] as $peran => $pendek) {
        $this->actingAs($this->pengendali)
            ->get(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, $peran]))
            ->assertOk()
            ->assertJsonPath('start_url', route("admin.turnamen.gelanggang.panel.{$peran}", [$this->tournament, $this->arena]))
            ->assertJsonPath('short_name', $pendek.' '.$this->arena->code);
    }
});

/*
 * Penolakan yang menawarkan "pindah paksa" harus bisa dikenali panel tanpa
 * membaca kalimatnya. Kunci `dapat_dipaksa` itulah yang menyalakan tombol
 * paksa di panel kendali; tanpa penanda ini, pesan galatnya menyuruh
 * pengendali melakukan sesuatu yang tidak disediakan layarnya.
 */
it('menandai penolakan yang bisa ditembus paksa di galat validasi', function () {
    $berikutnya = ($this->buatPartai)(2);

    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    (new MatchTimer)->mulaiBabak($this->match, 1);

    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => $berikutnya->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['aksi', 'dapat_dipaksa']);
});

it('mengosongkan gelanggang paksa lewat panel kendali', function () {
    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    (new MatchTimer)->mulaiBabak($this->match, 1);

    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => null,
            'paksa' => true,
        ])
        ->assertOk();

    expect($this->arena->fresh()->active_match_id)->toBeNull();
});

it('menolak mengosongkan gelanggang tanpa paksa saat partai masih berjalan', function () {
    $this->pointer->tunjuk($this->arena, $this->match, $this->pengendali);
    (new MatchTimer)->mulaiBabak($this->match, 1);

    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.gelanggang.panel.partai-aktif', [$this->tournament, $this->arena]), [
            'match_id' => null,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['aksi', 'dapat_dipaksa']);

    expect($this->arena->fresh()->active_match_id)->toBe($this->match->id);
});

/*
 * Panel kendali harus MENYEDIAKAN jalan paksa itu, bukan cuma menyebutnya di
 * pesan galat.
 */
it('menyediakan tombol pindah paksa di panel kendali', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.gelanggang.panel.kendali', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('paksaTertunda', false);
});

/*
 * Layar tunggu menjanjikan panel yang terbuka sendiri begitu partai
 * ditetapkan. Siaran `gelanggang.partai` memang tiba, tapi markup panelnya
 * tidak ada di layar itu -- satu-satunya cara menepati janjinya adalah memuat
 * ulang halaman, dan penandanyalah yang memberi tahu klien kapan.
 */
it('menandai layar tunggu supaya panel memuat ulang saat partai ditetapkan', function () {
    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Menunggu pengendali memilih partai')
        /*
         * Penandanya dicari dalam bentuk yang benar-benar dicetak @js():
         * JSON di dalam JSON.parse(), dengan setiap tanda kutip ditulis
         * sebagai escape unicode. `chr(92)` dipakai supaya garis miring
         * terbaliknya tidak perlu ikut di-escape di berkas ini.
         */
        ->assertSee(str_replace('"', chr(92).'u0022', '"menunggu":true'), false);
});
