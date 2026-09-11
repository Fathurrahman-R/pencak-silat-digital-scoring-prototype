<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\ArenaTayang;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
use App\Models\Penalty;
use App\Models\Registration;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Scoring\MatchTimer;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([ResourceSeeder::class, RoleSeeder::class, SilatResourceSeeder::class, SilatRoleSeeder::class]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $this->arena = Arena::factory()->for($this->tournament)->create();

    $this->match = SilatMatch::create([
        'arena_id' => $this->arena->id,
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'blue_registration_id' => Registration::factory()->for($kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id])->id,
        'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    /*
     * Peran saja tidak cukup untuk membuka panel partai: wasit dan juri harus
     * ditugaskan ke partainya, dan operator harus memegang gelanggangnya.
     * Penugasan itu dipasang di sini supaya tiap uji di bawah menguji apa yang
     * memang ingin diujinya -- tampilan panel -- bukan mengulang penyiapan
     * penugasan yang sama enam belas kali.
     *
     * Penolakan bagi yang TIDAK ditugaskan diuji terpisah di AparatPartaiTest.
     */
    /*
     * Panel wasit dan juri sekarang dibuka lewat alamat GELANGGANG, dan isinya
     * mengikuti partai yang ditunjuk pengendali. Pointer karena itu perlu
     * ditetapkan sebelum panelnya diuji -- tanpa itu yang tampil layar tunggu,
     * bukan papan tombolnya.
     */
    ArenaTayang::updateOrCreate(
        ['arena_id' => $this->arena->id],
        ['tayang_type' => ArenaTayang::TANDING, 'tayang_id' => $this->match->id, 'disetel_pada' => now()],
    );

    $this->buatUser = function (string $peran) {
        $user = User::factory()->create();
        $user->syncRoles([peranSistem($peran)]);

        match ($peran) {
            'wasit' => MatchOfficial::create([
                'match_id' => $this->match->id,
                'user_id' => $user->id,
                'role' => MatchOfficial::ROLE_WASIT,
                'number' => 1,
            ]),
            'juri' => MatchOfficial::create([
                'match_id' => $this->match->id,
                'user_id' => $user->id,
                'role' => MatchOfficial::ROLE_JURI,
                'number' => $this->match->officials()->where('role', MatchOfficial::ROLE_JURI)->count() + 1,
            ]),
            'operator-it' => $this->arena->operators()->syncWithoutDetaching([$user->id]),
            default => null,
        };

        return $user;
    };
});

it('menampilkan panel operator dan menyisipkan konfigurasi alamat aksi', function () {
    $operator = ($this->buatUser)('operator-it');

    /*
     * Eyebrow "OPERATOR GELANGGANG" yang dulu ada di kepala panel dibuang
     * pada rombak Digital Scoring -- kepala kolom kanan sekarang menyebut
     * gelanggang dan nomor partainya langsung, penanda yang lebih berguna
     * daripada label peran yang statis.
     *
     * Nomornya TIDAK lagi dicetak Blade: sejak panel mengikuti gelanggang, ia
     * datang dari blok `identitas` di payload state, kalau tidak kepala panel
     * membeku menyebut partai yang sudah ditinggalkan sementara isinya sudah
     * berganti. Yang diuji karena itu bindingnya, dan nomor partainya diuji di
     * tempat ia sekarang benar-benar lahir -- endpoint state.
     */
    $this->actingAs($operator)
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('identitas.partai', false)
        ->assertSee('partaiPanel', false)
        ->assertSee('timerMulai', false);

    $this->actingAs($operator)
        ->get(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('identitas.partai', $this->match->id);
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
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
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
        /*
         * Jatuhan bukan wewenang dewan: ia diterbitkan wasit di gelanggang.
         *
         * Yang diperiksa PEMANGGIL AKSINYA, bukan kata "Jatuhan" -- alasan
         * yang sama dengan uji panel wasit di bawah. Sejak papan hasil
         * dipasang di panel ini, kata itu memang muncul sebagai label baris
         * rincian: "berapa jatuhan yang menyusun skor akhir" adalah bacaan,
         * bukan kendali, dan justru itu yang dibutuhkan peninjau.
         */
        ->assertDontSee('terbitkanJatuhan(sudut)', false);
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
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
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
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
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
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
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
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
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
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
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
        ->get(route('admin.turnamen.gelanggang.panel.wasit', [$this->tournament, $this->arena]))
        ->assertForbidden();
});

it('menampilkan panel juri lengkap dengan tautan manifest PWA', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('partaiPanel', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, 'juri']), false);
});

it('menolak wasit membuka panel juri -- itu bukan resource penilaian.create miliknya', function () {
    $wasit = ($this->buatUser)('wasit');

    $this->actingAs($wasit)
        ->get(route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
        ->assertForbidden();
});

it('menyajikan manifest PWA per gelanggang dengan start_url yang tidak pernah basi', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->get(route('admin.turnamen.gelanggang.panel.manifest', [$this->tournament, $this->arena, 'juri']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', route('admin.turnamen.gelanggang.panel.juri', [$this->tournament, $this->arena]))
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

/*
 * Rincian skor sampai sekarang cuma hidup di overlay siaran dan berita acara
 * PDF. Petugas gelanggang yang ingin tahu dari mana angka akhirnya datang
 * harus membuka vMix atau mencetak berkas -- di tengah kejuaraan, keduanya
 * bukan jawaban.
 */
it('memasang papan hasil di panel operator', function () {
    $operator = ($this->buatUser)('operator-it');

    $this->actingAs($operator)
        ->get(route('admin.turnamen.gelanggang.panel.papan', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertSee('Skor per babak')
        ->assertSee('Rincian')
        ->assertSee('Poin akhir');
});

/** Peninjau butuh rincian angka SEBELUM menekan sahkan, bukan sesudah. */
it('memasang papan hasil di panel dewan wasit juri', function () {
    $dewan = ($this->buatUser)('pengawas-wasit-juri');

    $this->actingAs($dewan)
        ->get(route('admin.turnamen.partai.dewan-juri', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertSee('Skor per babak')
        ->assertSee('Poin akhir');
});

it('mengirim rincian teknik kedua sudut di payload state panel', function () {
    $operator = ($this->buatUser)('operator-it');

    ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => 'red',
        'point_type' => 'tendangan', 'value' => 2, 'server_ts' => now(),
    ]);

    $this->actingAs($operator)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('teknik.merah.tendangan', 1)
        ->assertJsonPath('teknik.merah.pukulan', 0)
        ->assertJsonPath('teknik.biru.jatuhan', 0);
});

/*
 * Pointer tayang pindah dari kolom di `arenas` ke tabelnya sendiri
 * (`arena_tayang`), dan itu memindahkan satu pembacaan gratis jadi satu query.
 * Endpoint inilah yang paling tidak boleh menanggungnya: ia ditarik tiap panel
 * yang terbuka, tiap ada siaran, ditambah sekali tiap dua puluh detik selama
 * babak berjalan.
 *
 * Yang dijaga bukan angka pastinya -- itu akan berubah tiap kali payload
 * bertambah -- melainkan bahwa jumlahnya tidak tumbuh mengikuti banyaknya
 * gelanggang. Pointer yang dibaca per gelanggang di dalam perulangan adalah
 * bentuk N+1 yang tidak terlihat sampai kejuaraan punya dua belas matras.
 */
it('tidak menambah query saat kejuaraan punya lebih banyak gelanggang', function () {
    $operator = ($this->buatUser)('operator-it');

    $hitung = function () use ($operator): int {
        $jumlah = 0;
        $pendengar = function () use (&$jumlah) {
            $jumlah++;
        };

        DB::listen($pendengar);

        $this->actingAs($operator)
            ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
            ->assertOk();

        return $jumlah;
    };

    /*
     * Panggilan pertama dibuang: ia ikut menanggung pemanasan -- cache izin,
     * konfigurasi, peta resource. Membandingkannya dengan panggilan kedua
     * mengukur pemanasan itu, bukan jumlah gelanggang, dan hasilnya justru
     * TURUN (37 lalu 26) sehingga uji ini lolos atau gagal karena alasan yang
     * sama sekali bukan yang dijaganya.
     */
    $hitung();

    $satuGelanggang = $hitung();

    // Sebelas gelanggang lain, masing-masing dengan pointernya sendiri.
    foreach (range(1, 11) as $urutan) {
        $lain = Arena::factory()->for($this->tournament)->create(['sort_order' => $urutan]);

        ArenaTayang::create([
            'arena_id' => $lain->id,
            'tayang_type' => ArenaTayang::TANDING,
            'tayang_id' => $this->match->id,
            'disetel_pada' => now(),
        ]);
    }

    expect($hitung())->toBe($satuGelanggang);
});

/*
 * Lajur tekanan juri di panel Dewan Wasit Juri.
 *
 * Riwayat panel hanya memperlihatkan nilai yang TERBIT. Tekanan yang tidak
 * cukup disepakati tidak meninggalkan jejak di layar mana pun -- padahal itu
 * yang ditanyakan pelatih saat memprotes: "juri saya menekan, kenapa tidak jadi
 * nilai?". Sebelum ini jawabannya cuma bisa dicari di basis data, di tengah
 * tenggat protes lima menit.
 */
it('mengirim tekanan juri yang tidak jadi nilai kepada peninjau hasil', function () {
    // buatUser sudah menugaskan juri ke partai ini; menambahkannya lagi
    // menabrak unique(match_id, user_id).
    $juri = ($this->buatUser)('juri');

    (new MatchTimer)->mulaiBabak($this->match, 1);

    // Satu juri saja -- di bawah ambang, jadi tidak pernah jadi nilai.
    $this->actingAs($juri)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])->assertSessionHasNoErrors();

    $pengawas = ($this->buatUser)('pengawas-wasit-juri');

    $muatan = $this->actingAs($pengawas)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonCount(1, 'tekanan')
        // Sendirian: sah, tercatat, tapi tidak menemukan juri lain di jendela
        // kesepakatan. Inilah keadaan yang selama ini tidak terlihat.
        ->assertJsonPath('tekanan.0.status', 'sendirian')
        ->assertJsonPath('tekanan.0.corner', 'red')
        ->json();

    expect($muatan['tekanan'][0]['juri'])->not->toBeNull()
        // Riwayat nilai tetap kosong -- tidak ada nilai yang terbit.
        ->and(collect($muatan['riwayat'])->where('tipe', 'nilai'))->toBeEmpty();
});

/*
 * Panel juri menarik endpoint yang SAMA. Tekanan mentah tidak ada gunanya di
 * sana, dan ongkosnya satu kueri pada jalur yang ditarik tiap kali sebuah nilai
 * terbit.
 */
it('tidak mengirim lajur tekanan kepada juri', function () {
    $juri = ($this->buatUser)('juri');

    $this->actingAs($juri)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonPath('tekanan', null);
});

/*
 * Partai yang riwayatnya sudah dipangkas menjawab dengan daftar kosong, bukan
 * daftar yang seolah tidak pernah ada tekanan. Pembedanya
 * `riwayat_dipangkas_pada`, yang sudah lama ikut di payload.
 */
it('mengosongkan lajur tekanan pada partai yang riwayatnya dipangkas', function () {
    $pengawas = ($this->buatUser)('pengawas-wasit-juri');

    $this->match->forceFill(['judge_inputs_dipangkas_pada' => now()])->save();

    $this->actingAs($pengawas)
        ->getJson(route('admin.turnamen.gelanggang.panel.state', [$this->tournament, $this->arena]))
        ->assertOk()
        ->assertJsonCount(0, 'tekanan')
        ->assertJsonPath('riwayat_dipangkas_pada', fn ($nilai) => $nilai !== null);
});
