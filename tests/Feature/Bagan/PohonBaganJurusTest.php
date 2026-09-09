<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Contingent;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\PohonBagan;
use App\Support\Bagan\SusunBaganJurus;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Bagan Jurus digambar POHON YANG SAMA dengan bagan Tanding.
 *
 * Bukan demi kerapian: PohonBagan dipakai empat permukaan sekaligus -- halaman
 * panitia, halaman publik, overlay siaran, dan cetak PDF -- dan bagan yang
 * dihitung ulang khusus Jurus akan menyimpang diam-diam dari keempatnya. Pohon
 * yang garisnya meleset menyesatkan pembacanya tentang siapa bertemu siapa,
 * kesalahan paling mahal di layar itu.
 *
 * Uji pertama di berkas ini yang paling menentukan: geometri bagan Tanding
 * TIDAK BOLEH berubah oleh generalisasi ini.
 */

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

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
    $this->pohon = new PohonBagan;

    $this->panitia = User::factory()->create();
    $this->panitia->assignRole('super-admin');
});

/*
 * Bagan Tanding berukuran sama harus menghasilkan koordinat yang sama persis
 * dengan bagan Jurus: geometrinya memang tidak tahu apa pun tentang kategori.
 */
it('menggambar bagan Jurus dengan geometri yang sama dengan bagan Tanding', function () {
    ($this->daftarkan)(4);
    $baganJurus = $this->susun->untukNomor($this->nomor, acak: false);

    $kelas = $this->tournament->weightClasses()->firstOrFail();
    $baganTanding = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 4]);

    foreach (range(1, 4) as $posisi) {
        BracketSlot::create([
            'bracket_id' => $baganTanding->id,
            'position' => $posisi,
            'registration_id' => null,
        ]);
    }

    $jurus = ($this->pohon)($baganJurus->fresh());
    $tanding = ($this->pohon)($baganTanding->fresh());

    expect($jurus['lebar'])->toBe($tanding['lebar'])
        ->and($jurus['tinggi'])->toBe($tanding['tinggi'])
        ->and($jurus['slot_tinggi'])->toBe($tanding['slot_tinggi'])
        ->and(collect($jurus['kolom'])->pluck('x')->all())
        ->toBe(collect($tanding['kolom'])->pluck('x')->all())
        ->and($jurus['garis'])->toBe($tanding['garis']);
});

it('menamai babak bagan Jurus dihitung mundur dari final', function () {
    ($this->daftarkan)(8);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    expect(collect(($this->pohon)($bagan)['kolom'])->pluck('judul')->all())
        ->toBe(['Perempat final', 'Semifinal', 'Final']);
});

it('mewarnai slot bagan Jurus menurut sudutnya', function () {
    ($this->daftarkan)(4);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $babakPertama = collect(($this->pohon)($bagan)['kolom'])->first()['slot'];

    expect(collect($babakPertama)->pluck('sudut')->all())
        ->toBe(['merah', 'biru', 'merah', 'biru']);
});

/*
 * Pasangan bye tidak digambar di kolom pertama: partainya tidak pernah
 * dipertandingkan, dan memajangnya membuat pembaca bagan mengira ada
 * pertandingan yang ia lewatkan.
 */
it('melewati pasangan bye di kolom pertama bagan Jurus', function () {
    ($this->daftarkan)(3);
    $bagan = $this->susun->untukNomor($this->nomor, acak: false);

    $babakPertama = collect(($this->pohon)($bagan)['kolom'])->first()['slot'];

    expect($babakPertama)->toHaveCount(2);
});

it('menampilkan halaman bagan Jurus', function () {
    ($this->daftarkan)(4);
    $this->susun->untukNomor($this->nomor, acak: false);

    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jurus.bagan.show', [$this->tournament, $this->nomor]))
        ->assertOk()
        ->assertViewIs('admin.jurus.bagan')
        ->assertSee('Sudut biru tampil lebih dulu');
});

it('mencetak bagan Jurus sebagai PDF', function () {
    ($this->daftarkan)(4);
    $this->susun->untukNomor($this->nomor, acak: false);

    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jurus.bagan.cetak', [$this->tournament, $this->nomor]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('menolak menggambar bagan nomor yang belum disusun', function () {
    $this->actingAs($this->panitia)
        ->get(route('admin.turnamen.jurus.bagan.show', [$this->tournament, $this->nomor]))
        ->assertNotFound();
});

it('menolak pengguna tanpa izin bagan', function () {
    ($this->daftarkan)(4);
    $this->susun->untukNomor($this->nomor, acak: false);

    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->get(route('admin.turnamen.jurus.bagan.show', [$this->tournament, $this->nomor]))
        ->assertForbidden();
});
