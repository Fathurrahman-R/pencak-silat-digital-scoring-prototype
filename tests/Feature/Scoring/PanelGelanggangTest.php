<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

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

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([$peran]);

        return $user;
    };
});

it('menampilkan panel operator dan menyisipkan konfigurasi alamat aksi', function () {
    $operator = ($this->buatUser)('operator-it');

    /*
     * Eyebrow "OPERATOR GELANGGANG" yang dulu ada di kepala panel dibuang
     * pada rombak Digital Scoring -- kepala kolom kanan sekarang menyebut
     * gelanggang dan nomor partainya langsung, penanda yang lebih berguna
     * daripada label peran yang statis. Sama seperti panel wasit di bawah:
     * eyebrow hanya hiasan, jadi yang diuji adalah identitas partai yang
     * sesungguhnya dirender.
     */
    $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Partai '.$this->match->id)
        ->assertSee('partaiPanel', false)
        ->assertSee('timerMulai', false);
});

it('menampilkan panel wasit', function () {
    $wasit = ($this->buatUser)('wasit');

    /*
     * Penandanya istilah naskah, bukan eyebrow "WASIT" yang dulu ada di kepala.
     * Kepala panel dibuat setipis mungkin ketika layarnya dipindah ke orientasi
     * landscape -- tinggi 390px seluruhnya dibutuhkan tombol. Istilah Pasal
     * 11.6.d.4 justru penanda yang lebih benar: ia WAJIB ada di panel wasit dan
     * tidak boleh hilang, sedangkan eyebrow hanya hiasan.
     */
    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Pembinaan')
        ->assertSee('Teguran')
        ->assertSee('Peringatan');
});

it('menampilkan panel dewan juri', function () {
    $pengawas = ($this->buatUser)('pengawas-wasit-juri');

    $this->actingAs($pengawas)
        ->get(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->assertOk()
        // Sebutan badan ini disatukan jadi 'Dewan Wasit Juri', mengikuti label
        // role di SilatRoleSeeder dan blok tanda tangan berita acara.
        ->assertSee('DEWAN WASIT JURI')
        // Jatuhan bukan wewenang dewan: ia diterbitkan wasit di gelanggang.
        ->assertDontSee('Jatuhan');
});

it('memasang kendali jatuhan di panel wasit', function () {
    /*
     * Jatuhan sederajat dengan hukuman: nilainya mutlak dan diputuskan orang
     * yang berdiri di gelanggang, jadi tombolnya berdiri di panel yang sama
     * dengan Pembinaan, Teguran, dan Peringatan.
     *
     * Yang diperiksa PEMANGGIL AKSINYA, bukan kata "Jatuhan": kata itu juga
     * dipakai daftar jenis verifikasi di panel yang sama, jadi uji yang
     * mencarinya tetap hijau walau tombolnya hilang sama sekali. Itu persis
     * yang sempat terjadi -- satu direktif liar di dalam komentar Blade
     * menelan seluruh blok tombolnya, dan ujinya tidak menyadari apa pun.
     */
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('terbitkanJatuhan(sudut)', false)
        // Sarannya ikut: wasit yang sempat bertanya ke juri membacanya di sini.
        ->assertSee('saranJatuhan', false);
});

it('menyertakan hitungan teknik di state partai', function () {
    /*
     * Angka ini yang membuat wasit tahu tekanan berikutnya mengakhiri partai
     * atau tidak. Selama ia tidak ada di state, tidak ada panel yang bisa
     * menampilkannya berapa pun rapinya tata letaknya.
     */
    $wasit = ($this->buatUser)('wasit');

    $state = $this->actingAs($wasit)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->json();

    expect($state['hitungan']['merah'])->toBe(['jumlah' => 0, 'beruntun' => 0, 'terakhir' => null])
        ->and($state['hitungan']['ambang_beruntun'])->toBe(3)
        ->and($state['hitungan']['ambang_teguran'])->toBe(9)
        ->and($state['hitungan']['ambang_mutlak'])->toBe(10);
});

it('menampilkan riwayat hitungan di panel wasit', function () {
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('hitunganTeknik[kunciSisi].beruntun', false)
        ->assertSee('Belum pernah dihitung babak ini.');
});

it('tidak memasang indikator jatuhan juri di panel operator', function () {
    /*
     * Indikator teknik di panel operator menghitung berapa juri yang menekan
     * teknik yang sama. Jatuhan tidak lagi ditekan juri, jadi barisnya tidak
     * akan pernah menyala -- dan indikator yang selamanya kosong terbaca
     * operator sebagai juri yang tidak menekan, bukan sebagai teknik yang
     * memang bukan urusan juri.
     */
    $operator = ($this->buatUser)('operator-it');

    $halaman = $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertOk();

    $halaman->assertSee('Pukulan')
        ->assertSee('Tendangan')
        ->assertDontSee('indikatorTeknik?.red?.jatuhan', false);
});

it('tidak lagi memasang tombol jatuhan di panel juri', function () {
    /*
     * Jatuhan bukan penilaian yang dikonsensuskan tiga juri: nilainya mutlak,
     * keputusan Dewan Wasit Juri. Tombol yang tetap terpasang mengundang juri
     * menekannya dan mendapat penolakan yang tidak ia mengerti.
     */
    $juri = ($this->buatUser)('juri');

    $halaman = $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertOk();

    $halaman->assertSee('Pukulan')
        ->assertSee('Tendangan')
        ->assertDontSee('Jatuhan');
});

it('mengizinkan juri melihat panel operator sebagai pemantau, meski tombolnya tersembunyi lewat @resource', function () {
    // Juri memang punya partai.view (memantau jalannya partai) tapi bukan
    // partai.update/manage, jadi tombol kendali di halaman ini tersembunyi.
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertOk();
});

it('menolak pengguna tanpa peran pertandingan membuka panel operator', function () {
    $tanpaPeran = User::factory()->create();

    $this->actingAs($tanpaPeran)
        ->get(route('admin.turnamen.partai.operator', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menolak juri membuka panel wasit', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.wasit', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menampilkan panel juri lengkap dengan tautan manifest PWA', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('partaiPanel', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('admin.turnamen.partai.juri.manifest', [$this->tournament, $this->match]), false);
});

it('menolak wasit membuka panel juri -- itu bukan resource penilaian.create miliknya', function () {
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertForbidden();
});

it('menyajikan manifest PWA per partai dengan start_url menunjuk balik ke partai itu', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.partai.juri.manifest', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', route('admin.turnamen.partai.juri', [$this->tournament, $this->match]))
        ->assertJsonPath('display', 'fullscreen');
});

/*
 * Hasil yang sudah disahkan tidak bisa diubah lagi. Janji itu ditulis di panel
 * Dewan Wasit Juri, dicetak di berita acara, dan jadi dasar kenapa bagan boleh
 * maju ke tahap berikutnya -- tapi sampai ditemukan lewat pemeriksaan manual,
 * tidak ada satu pun yang menegakkannya: nilai dan hukuman masih bisa
 * dibatalkan sesudah pengesahan.
 *
 * Koreksi sesudah pengesahan bukan tidak mungkin, tapi jalurnya protes manajer
 * (Pasal 15 ayat 4), bukan tombol Batalkan di panel.
 */
it('menolak membatalkan nilai setelah hasil partai disahkan', function () {
    $dewan = ($this->buatUser)('pengawas-wasit-juri');

    $nilai = ScoreEvent::create([
        'match_id' => $this->match->id,
        'round' => 1,
        'corner' => 'red',
        'point_type' => 'pukulan',
        'value' => 1,
        'server_ts' => now(),
    ]);

    $this->match->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $this->match->red_registration_id,
        'win_reason' => 'angka',
        'ratified_at' => now(),
        'ratified_by' => $dewan->id,
    ]);

    $this->actingAs($dewan)
        ->from(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->post(route('admin.turnamen.partai.nilai.batal', [$this->tournament, $this->match, $nilai]), [
            'alasan' => 'koreksi dewan juri',
        ])
        ->assertSessionHasErrors('match');

    expect($nilai->fresh()->voided_at)->toBeNull();
});

it('menolak membatalkan hukuman setelah hasil partai disahkan', function () {
    $dewan = ($this->buatUser)('pengawas-wasit-juri');

    $hukuman = Penalty::create([
        'match_id' => $this->match->id,
        'round' => 1,
        'corner' => 'red',
        'tier' => 'teguran',
        'level' => 1,
        'violation_level' => 'sedang',
        'value' => -1,
    ]);

    $this->match->update([
        'status' => SilatMatch::STATUS_SELESAI,
        'winner_registration_id' => $this->match->red_registration_id,
        'win_reason' => 'angka',
        'ratified_at' => now(),
        'ratified_by' => $dewan->id,
    ]);

    $this->actingAs($dewan)
        ->from(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->post(route('admin.turnamen.partai.hukuman.batal', [$this->tournament, $this->match, $hukuman]), [
            'alasan' => 'koreksi dewan juri',
        ])
        ->assertSessionHasErrors('match');

    expect($hukuman->fresh()->voided_at)->toBeNull();
});
