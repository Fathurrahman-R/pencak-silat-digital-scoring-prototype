<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Contingent;
use App\Models\MatchOfficial;
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

    /*
     * Timer, pengakhiran partai, dan pergantian jadwal pindah dari Operator IT
     * ke peran Pengendali Gelanggang. Yang menjalankan aksi-aksi itu di berkas
     * ini karena itu pengendali, bukan operator -- operator turun jadi papan
     * tampilan dan tidak lagi memimpin jalannya partai.
     */
    $this->pengendali = ($this->buatUser)('pengendali-gelanggang');
    $this->wasit = ($this->buatUser)('wasit');
    $this->juri1 = ($this->buatUser)('juri');
    $this->juri2 = ($this->buatUser)('juri');
    $this->pengawas = ($this->buatUser)('pengawas-wasit-juri');
    $this->ketua = ($this->buatUser)('ketua-pertandingan');

    /*
     * Wasit dan juri baru boleh bertindak di partai yang ditugaskan kepada
     * mereka, jadi penugasannya harus ada sebelum aksinya diuji. Pengawas
     * dan ketua sengaja tidak ditugaskan: kewenangan mereka lintas
     * gelanggang, dan itu ikut teruji di sini.
     *
     * Pengendali terikat gelanggang, bukan partai, jadi partainya perlu
     * dijadwalkan ke sebuah gelanggang lebih dulu.
     */
    $gelanggang = Arena::factory()->for($this->tournament)->create();
    $gelanggang->pengendali()->attach($this->pengendali);
    $this->match->update(['arena_id' => $gelanggang->id, 'order_in_arena' => 1]);

    MatchOfficial::create([
        'match_id' => $this->match->id,
        'user_id' => $this->wasit->id,
        'role' => MatchOfficial::ROLE_WASIT,
    ]);

    foreach ([$this->juri1, $this->juri2] as $nomor => $juri) {
        MatchOfficial::create([
            'match_id' => $this->match->id,
            'user_id' => $juri->id,
            'role' => MatchOfficial::ROLE_JURI,
            'number' => $nomor + 1,
        ]);
    }
});

it('menampilkan state partai lewat resync', function () {
    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('match.status', SilatMatch::STATUS_TERJADWAL)
        ->assertJsonPath('skor_total.merah', 0);
});

it('pengendali memulai babak lewat timer', function () {
    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->match->fresh()->current_round)->toBe(1)
        ->and($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('juri tidak boleh mengendalikan timer', function () {
    $this->actingAs($this->juri1)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertForbidden();
});

it('menjeda dan melanjutkan babak yang sedang berjalan', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.partai.timer.jeda', [$this->tournament, $this->match]))
        ->assertRedirect()->assertSessionHas('success');

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.partai.timer.lanjut', [$this->tournament, $this->match]))
        ->assertRedirect()->assertSessionHas('success');
});

it('juri mengirim nilai dan skor terbit setelah dua juri sepakat', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $kirim = fn (User $juri) => $this->actingAs($juri)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ]);

    $kirim($this->juri1)->assertRedirect()->assertSessionHas('success');
    $kirim($this->juri2)->assertRedirect()->assertSessionHas('success');

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(1)
        ->and($this->match->fresh())->not->toBeNull();
});

it('menolak nilai yang dikirim sebelum babak dimulai dan memberi peringatan bukan galat', function () {
    $this->actingAs($this->juri1)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');
});

it('wasit menjatuhkan hukuman', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'blue', 'tingkat' => 'berat', 'catatan' => 'menyerang area terlarang',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Penalty::where('match_id', $this->match->id)->count())->toBe(1);
});

it('juri tidak boleh menjatuhkan hukuman', function () {
    $this->actingAs($this->juri1)
        ->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'blue', 'tingkat' => 'berat',
        ])
        ->assertForbidden();
});

it('hitungan sampai sepuluh mengakhiri partai secara otomatis', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'blue', 'hitungan' => 10,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $partai = $this->match->fresh();
    expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($partai->win_reason)->toBe('mutlak')
        ->and($partai->winner_registration_id)->toBe($partai->red_registration_id);
});

/*
 * Pasal 11.6.c huruf b: "Jika kedua Pesilat tidak segera bangkit, maka
 * dilakukan hitungan teknik untuk keduanya."
 *
 * Bukan dua tekanan hitungan biasa. Jalur satu sudut menjatuhkan Teguran di
 * hitungan ke-9 dan mengakhiri partai dengan pemenang di ke-10; keduanya
 * keliru saat yang jatuh adalah kedua pesilat, karena naskah justru menyuruh
 * menimbang berat badan atau menghitung nilai terbanyak.
 */
it('wasit mencatat hitungan serentak untuk kedua sudut tanpa mengakhiri partai', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'hitungan' => 10, 'serentak' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $partai = $this->match->fresh();

    expect($partai->technicalCounts()->count())->toBe(2)
        ->and($partai->technicalCounts()->pluck('corner')->unique())->toHaveCount(2)
        ->and($partai->status)->toBe(SilatMatch::STATUS_BERLANGSUNG)
        ->and($partai->winner_registration_id)->toBeNull()
        // Tidak ada Teguran: hitungan serentak tidak menghukum siapa pun.
        ->and(Penalty::count())->toBe(0);
});

/*
 * Tawaran penyelesaiannya harus SAMPAI ke panel. Selama `tawaran_serentak`
 * tidak terbaca klien, keadaan ini terjadi tanpa ada yang memberi tahu
 * siapa pun bahwa naskah menyediakan jalan keluarnya.
 */
it('menyertakan tawaran penyelesaian hitungan serentak di state', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'hitungan' => 10, 'serentak' => true,
        ]);

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('tawaran_serentak.sebab', 'berat_badan_teringan');
});

it('menolak hitungan satu sudut tanpa menyebut sudutnya', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.hitungan', [$this->tournament, $this->match]), [
            'babak' => 1, 'hitungan' => 5,
        ])
        ->assertSessionHasErrors('corner');
});

it('pengendali mengakhiri partai secara manual dengan sebab WMP', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.partai.akhiri', [$this->tournament, $this->match]), [
            'corner' => 'red', 'sebab' => 'wmp',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $partai = $this->match->fresh();
    expect($partai->status)->toBe(SilatMatch::STATUS_SELESAI)
        ->and($partai->win_reason)->toBe('wmp')
        ->and($partai->winner_registration_id)->toBe($partai->red_registration_id);
});

it('wasit tidak boleh mengakhiri partai -- itu wewenang manage, bukan create hukuman', function () {
    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.akhiri', [$this->tournament, $this->match]), [
            'corner' => 'red', 'sebab' => 'wmp',
        ])
        ->assertForbidden();
});

it('ketua pertandingan mengesahkan hasil partai yang sudah selesai', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.akhiri', [$this->tournament, $this->match]), ['corner' => 'red', 'sebab' => 'wmp']);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.sahkan', [$this->tournament, $this->match]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $partai = $this->match->fresh();
    expect($partai->disahkan())->toBeTrue()
        ->and($partai->ratified_by)->toBe($this->ketua->id);
});

it('menolak mengesahkan partai yang belum punya pemenang', function () {
    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.partai.sahkan', [$this->tournament, $this->match]))
        ->assertSessionHasErrors('match');
});

it('pengawas membatalkan nilai yang sudah terbit tanpa menghapus riwayatnya', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $kirim = fn (User $juri) => $this->actingAs($juri)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
        ]);
    $kirim($this->juri1);
    $kirim($this->juri2);

    $scoreEvent = ScoreEvent::where('match_id', $this->match->id)->firstOrFail();

    $this->actingAs($this->pengawas)
        ->post(route('admin.turnamen.partai.nilai.batal', [$this->tournament, $this->match, $scoreEvent]), [
            'alasan' => 'salah lihat sudut',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect(ScoreEvent::count())->toBe(1)
        ->and($scoreEvent->fresh()->dibatalkan())->toBeTrue();
});

it('wasit tidak boleh membatalkan nilai -- itu wewenang dewan juri', function () {
    $scoreEvent = ScoreEvent::create([
        'match_id' => $this->match->id, 'round' => 1, 'corner' => 'red',
        'point_type' => 'pukulan', 'value' => 1, 'server_ts' => now(),
    ]);

    $this->actingAs($this->wasit)
        ->post(route('admin.turnamen.partai.nilai.batal', [$this->tournament, $this->match, $scoreEvent]), [
            'alasan' => 'coba-coba',
        ])
        ->assertForbidden();
});

it('menyertakan hitungan teguran dan setelan peraturan di state', function () {
    $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk()
        ->assertJsonPath('hukuman.merah.teguran', 0)
        ->assertJsonPath('peraturan.jumlah_juri', 3)
        ->assertJsonPath('peraturan.ambang_sepakat', 2);
});

it('membalas JSON tipis alih-alih redirect saat panel meminta JSON', function () {
    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertOk()
        ->assertJson(['tipe' => 'success']);
});

it('membalas galat validasi sebagai JSON saat panel meminta JSON', function () {
    $this->actingAs($this->pengendali)
        ->postJson(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('babak');
});

it('menyertakan riwayat nilai dan hukuman untuk panel dewan juri', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);
    $this->actingAs($this->wasit)->post(route('admin.turnamen.partai.hukuman', [$this->tournament, $this->match]), [
        'babak' => 1, 'corner' => 'blue', 'tingkat' => 'berat',
    ]);

    $response = $this->actingAs($this->pengendali)
        ->getJson(route('admin.turnamen.partai.state', [$this->tournament, $this->match]))
        ->assertOk();

    $riwayat = $response->json('riwayat');
    expect($riwayat)->toHaveCount(1)
        ->and($riwayat[0]['tipe'])->toBe('hukuman')
        ->and($riwayat[0]['corner'])->toBe('blue');
});

it('tetap menyimpan aksi ke database walau server Reverb tidak terjangkau', function () {
    // Ditemukan lewat verifikasi manual di browser: sebelum diperbaiki, aksi
    // yang berhasil di database ikut gagal total begitu ShouldBroadcastNow
    // tidak bisa menjangkau Reverb -- gelanggang berhenti bekerja hanya
    // karena siaran realtimenya putus, padahal skor semestinya tetap sah
    // dicatat lokal. config() dipaksa ke 'reverb' dengan kredensial asli dari
    // .env supaya tanda tangan permintaannya valid, tapi host-nya tidak ada
    // server yang menyala di sana sehingga panggilannya betul-betul gagal.
    config(['broadcasting.default' => 'reverb']);

    $this->actingAs($this->pengendali)
        ->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->match->fresh()->current_round)->toBe(1)
        ->and($this->match->fresh()->status)->toBe(SilatMatch::STATUS_BERLANGSUNG);
});

it('menolak state partai yang bukan milik kejuaraan di alamat', function () {
    $turnamenLain = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($turnamenLain);

    $this->actingAs($this->pengendali)
        ->get(route('admin.turnamen.partai.state', [$turnamenLain, $this->match]))
        ->assertNotFound();
});

/*
 * Waktu tekan menurut perangkat juri, dibawa setiap tekanan.
 *
 * Dipakai panel yang mengantre tekanan selama jaringannya putus: yang
 * terkirim sesudah pulih tiba beberapa detik terlambat, dan tanpa kolom ini
 * jejak auditnya berbohong tentang kapan serangan itu dinilai.
 */
it('menyimpan waktu tekan dari perangkat juri tanpa menggantikan waktu server', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $ditekan = now()->subSeconds(4);

    $this->actingAs($this->juri1)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan',
            'client_ts' => $ditekan->toIso8601String(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $input = App\Models\JudgeInput::where('match_id', $this->match->id)->firstOrFail();

    expect($input->client_ts->timestamp)->toBe($ditekan->timestamp)
        ->and($input->server_ts->timestamp)->toBeGreaterThan($input->client_ts->timestamp);
});

/*
 * Konsensus tetap dihitung dari waktu server. Jam perangkat juri tidak
 * dipercaya: juri yang jamnya meleset -- atau disetel -- tidak boleh bisa
 * menggeser nilainya ke momen yang menguntungkan.
 */
it('tidak memakai waktu tekan perangkat untuk menghitung konsensus', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $kirim = fn (User $juri, string $clientTs) => $this->actingAs($juri)
        ->post(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan', 'client_ts' => $clientTs,
        ]);

    // Dua juri menekan pada saat yang sama menurut server, tapi jam perangkat
    // keduanya terpaut satu jam. Nilainya tetap terbit.
    $kirim($this->juri1, now()->subHour()->toIso8601String())->assertSessionHas('success');
    $kirim($this->juri2, now()->addHour()->toIso8601String())->assertSessionHas('success');

    expect(ScoreEvent::where('match_id', $this->match->id)->count())->toBe(1);
});

it('menolak waktu tekan yang bukan tanggal', function () {
    $this->actingAs($this->pengendali)->post(route('admin.turnamen.partai.timer.mulai', [$this->tournament, $this->match]), ['babak' => 1]);

    $this->actingAs($this->juri1)
        ->postJson(route('admin.turnamen.partai.nilai', [$this->tournament, $this->match]), [
            'babak' => 1, 'corner' => 'red', 'jenis' => 'pukulan', 'client_ts' => 'kemarin sore',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_ts');

    expect(App\Models\JudgeInput::where('match_id', $this->match->id)->count())->toBe(0);
});
