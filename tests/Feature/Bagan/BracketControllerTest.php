<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\BracketGenerator;
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

    $this->kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->buatPeserta = function (int $jumlah) {
        foreach (range(1, $jumlah) as $nomor) {
            $registration = Registration::factory()->for($this->kontingen)->terverifikasi()
                ->create(['weight_class_id' => $this->kelas->id]);

            $registration->athletes()->attach(
                Athlete::factory()->for($this->kontingen)->create(['name' => "Pesilat {$nomor}"]),
            );
        }
    };
});

it('menampilkan daftar kelas tanding dengan jumlah peserta sah', function () {
    ($this->buatPeserta)(3);

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.bagan.index', $this->tournament))
        ->assertOk()
        ->assertSee($this->kelas->name)
        ->assertSee('3 peserta sah');
});

it('menyusun bagan dari peserta yang sudah disahkan', function () {
    ($this->buatPeserta)(4);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.susun', [$this->tournament, $this->kelas]))
        ->assertRedirect(route('admin.turnamen.bagan.show', [$this->tournament, $this->kelas]));

    expect(Bracket::where('weight_class_id', $this->kelas->id)->firstOrFail()->size)->toBe(4);
});

it('menolak menyusun bagan dengan peserta kurang dari dua', function () {
    ($this->buatPeserta)(1);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.susun', [$this->tournament, $this->kelas]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Bracket::count())->toBe(0);
});

it('menukar tempat sebelum bagan dikunci', function () {
    ($this->buatPeserta)(4);
    $bracket = (new BracketGenerator)->untukKelas($this->kelas, acak: false);

    $slot1 = $bracket->slots()->where('position', 1)->firstOrFail()->registration_id;
    $slot2 = $bracket->slots()->where('position', 2)->firstOrFail()->registration_id;

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.tukar', [$this->tournament, $this->kelas]), [
            'posisi_a' => 1,
            'posisi_b' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($bracket->slots()->where('position', 1)->firstOrFail()->registration_id)->toBe($slot2)
        ->and($bracket->slots()->where('position', 2)->firstOrFail()->registration_id)->toBe($slot1);
});

it('menolak menukar tempat setelah bagan dikunci', function () {
    ($this->buatPeserta)(4);
    $bracket = (new BracketGenerator)->untukKelas($this->kelas);
    $bracket->update(['locked_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.tukar', [$this->tournament, $this->kelas]), [
            'posisi_a' => 1,
            'posisi_b' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('mengunci bagan dan mencatatnya ke jejak audit', function () {
    ($this->buatPeserta)(4);
    $bracket = (new BracketGenerator)->untukKelas($this->kelas);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.kunci', [$this->tournament, $this->kelas]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($bracket->fresh()->terkunci())->toBeTrue()
        ->and($bracket->fresh()->locked_by)->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'bagan.kunci')->where('auditable_id', $bracket->id)->exists())->toBeTrue();
});

it('mewajibkan alasan saat membuka kunci bagan dan mencatatnya ke jejak audit', function () {
    ($this->buatPeserta)(4);
    $bracket = (new BracketGenerator)->untukKelas($this->kelas);
    $bracket->update(['locked_at' => now(), 'locked_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.buka-kunci', [$this->tournament, $this->kelas]), [])
        ->assertSessionHasErrors('alasan');

    expect($bracket->fresh()->terkunci())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.bagan.buka-kunci', [$this->tournament, $this->kelas]), [
            'alasan' => 'Dua kontingen tertukar saat undian manual.',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($bracket->fresh()->terkunci())->toBeFalse()
        ->and(AuditLog::where('action', 'bagan.buka_kunci')->where('auditable_id', $bracket->id)->firstOrFail()->properties)
        ->toMatchArray(['alasan' => 'Dua kontingen tertukar saat undian manual.']);
});

it('menolak kelas tanding yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $kelasLain = $turnamenLain->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.turnamen.bagan.show', [$this->tournament, $kelasLain]))
        ->assertNotFound();
});

/*
 * --------------------------------------------------------------------
 * Tata letak pohon bagan
 * --------------------------------------------------------------------
 */

it('menyusun kolom pohon sebanyak babak, tidak lebih', function () {
    /*
     * Bagan 8 peserta punya TIGA babak: perempat final, semifinal, final.
     * Satu kolom kelebihan akan menggambar papan kosong berlabel "Final" di
     * sebelah final yang sebenarnya — dan panitia yang melihatnya menyimpulkan
     * ada babak yang belum terisi.
     */
    ($this->buatPeserta)(8);
    (new BracketGenerator)->untukKelas($this->kelas);

    $pohon = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}")
        ->assertOk()
        ->viewData('pohon');

    expect($pohon['kolom'])->toHaveCount(3)
        ->and(collect($pohon['kolom'])->pluck('judul')->all())
        ->toBe(['Perempat final', 'Semifinal', 'Final'])
        ->and($pohon['kolom'][0]['slot'])->toHaveCount(8)
        ->and($pohon['kolom'][1]['slot'])->toHaveCount(4)
        ->and($pohon['kolom'][2]['slot'])->toHaveCount(2);
});

it('menempatkan garis penghubung tepat di tengah slot pasangannya', function () {
    /*
     * Ini alasan tata letaknya dihitung sebagai koordinat mutlak, bukan flex.
     * Garis yang meleset satu-dua piksel terbaca sebagai bagan yang salah
     * sambung, dan itu kesalahan paling mahal di layar ini: kontingen
     * menyiapkan atlet untuk lawan yang keliru.
     */
    ($this->buatPeserta)(4);
    (new BracketGenerator)->untukKelas($this->kelas);

    $pohon = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}")
        ->assertOk()
        ->viewData('pohon');

    $tinggi = $pohon['slot_tinggi'];
    $tengah = fn (array $slot) => $slot['y'] + intdiv($tinggi, 2);

    $garisH = collect($pohon['garis'])->where('jenis', 'h');
    $garisV = collect($pohon['garis'])->where('jenis', 'v');

    // Tiap slot babak pertama punya garis mendatar sejajar tengahnya.
    foreach ($pohon['kolom'][0]['slot'] as $slot) {
        expect($garisH->contains(fn ($g) => $g['y'] === $tengah($slot)))->toBeTrue();
    }

    // Tiap slot babak kedua disambut garis mendatar sejajar tengahnya.
    foreach ($pohon['kolom'][1]['slot'] as $slot) {
        expect($garisH->contains(fn ($g) => $g['y'] === $tengah($slot)))->toBeTrue();
    }

    // Garis tegak berhenti tepat di tengah kedua slot yang disatukannya.
    $slotBabak1 = $pohon['kolom'][0]['slot'];
    expect($garisV->first()['y'])->toBe($tengah($slotBabak1[0]))
        ->and($garisV->first()['y'] + $garisV->first()['panjang'])->toBe($tengah($slotBabak1[1]));
});

it('mewarnai slot menurut sudut, dan membiarkan yang belum berpenghuni tetap netral', function () {
    /*
     * Slot ganjil adalah sudut merah — berlaku sebelum partainya dijadwalkan,
     * dan kontingen memakai nomor itu untuk menyiapkan sudutnya.
     *
     * Slot babak lanjut yang penghuninya belum pasti TIDAK diwarnai:
     * mewarnainya berarti menjanjikan sudut yang belum diputuskan.
     */
    ($this->buatPeserta)(4);
    (new BracketGenerator)->untukKelas($this->kelas);

    $pohon = $this->actingAs($this->admin)
        ->get("/admin/turnamen/{$this->tournament->id}/bagan/{$this->kelas->id}")
        ->assertOk()
        ->viewData('pohon');

    $babak1 = $pohon['kolom'][0]['slot'];

    expect($babak1[0]['sudut'])->toBe('merah')
        ->and($babak1[1]['sudut'])->toBe('biru')
        ->and($babak1[2]['sudut'])->toBe('merah')
        ->and($babak1[3]['sudut'])->toBe('biru');

    // Final belum punya penghuni: keduanya menunggu.
    foreach ($pohon['kolom'][1]['slot'] as $slot) {
        expect($slot['menunggu'])->toBeTrue();
    }
});
