<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\FormatJurus;
use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\JurusBattle;
use App\Models\JurusEvent;
use App\Models\JurusPerformance;
use App\Models\JurusScore;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Bagan\SusunBaganJurus;
use App\Support\Jurus\PutuskanBattle;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/*
 * Skor akhir yang SAMA tidak diputus sistem.
 *
 * Sebelum berkas ini, seri diserahkan ke JurusScoreCalculator::peringkat(),
 * yang memecahnya berjenjang dan berakhir pada UNDIAN. Artinya seorang pesilat
 * bisa tersingkir dari bagan gugur oleh angka acak yang tidak pernah
 * diumumkan kepada siapa pun -- dan yang tidak bisa dijelaskan kepada pelatih
 * yang menanyakannya di meja pertandingan.
 *
 * Rantai peringkat itu sendiri TIDAK dihapus: ia tetap aturan nomor berformat
 * peringkat dan rekap medali. Yang berubah hanya jalur battle.
 */

beforeEach(function () {
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

    $this->nilai = function (JurusPerformance $penampilan, float $nilai) {
        foreach (range(1, 6) as $i) {
            JurusScore::create([
                'performance_id' => $penampilan->id,
                'judge_user_id' => User::factory()->create()->id,
                'value' => $nilai,
            ]);
        }

        $penampilan->update([
            'status' => JurusPerformance::STATUS_SELESAI,
            'duration_ms' => 75_000,
            'ratified_at' => now(),
        ]);

        return $penampilan->refresh();
    };

    /**
     * Battle ronde pertama yang kedua sudutnya sudah dinilai dan disahkan.
     *
     * @return array{0: JurusBattle, 1: JurusPerformance, 2: JurusPerformance}
     */
    $this->battleDinilai = function (float $nilaiBiru, float $nilaiMerah): array {
        ($this->daftarkan)(4);
        $bagan = $this->susun->untukNomor($this->nomor, acak: false);
        $battle = $bagan->battles()->where('round', 1)->first();

        $penampilan = $this->susun->siapkanPenampilan($battle);

        // siapkanPenampilan mengembalikan biru lebih dulu -- Pasal 12.1.d.7.
        $biru = ($this->nilai)($penampilan->first(), $nilaiBiru);
        $merah = ($this->nilai)($penampilan->last(), $nilaiMerah);

        return [$battle->refresh(), $biru, $merah];
    };

    $this->ketua = User::factory()->create();
    $this->ketua->syncRoles(['ketua-pertandingan']);
});

it('menolak menetapkan pemenang saat seri tanpa pilihan sudut', function () {
    [$battle] = ($this->battleDinilai)(9.70, 9.70);

    expect(fn () => app(PutuskanBattle::class)($battle))
        ->toThrow(RuntimeException::class, 'Ketua Pertandingan yang menetapkan');
});

it('menyebut angka yang seri di dalam penolakannya', function () {
    [$battle] = ($this->battleDinilai)(9.70, 9.70);

    expect(fn () => app(PutuskanBattle::class)($battle))
        ->toThrow(RuntimeException::class, '9.70');
});

it('menolak menetapkan pemenang seri tanpa alasan', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.70, 9.70);

    expect(fn () => app(PutuskanBattle::class)(
        $battle,
        pemenangRegistrationId: $biru->registration_id,
        alasan: '   ',
    ))->toThrow(RuntimeException::class, 'alasannya wajib ditulis');
});

it('menetapkan pemenang seri yang dipilih Ketua, menyimpan alasannya', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.70, 9.70);

    $hasil = app(PutuskanBattle::class)(
        $battle,
        pemenangRegistrationId: $biru->registration_id,
        alasan: 'Kemantapan gerak lebih baik menurut Dewan Wasit Juri.',
        oleh: $this->ketua,
    );

    expect($hasil->winner_registration_id)->toBe($biru->registration_id)
        ->and($hasil->win_reason)->toBe('keputusan_ketua')
        ->and($hasil->keputusan_alasan)->toBe('Kemantapan gerak lebih baik menurut Dewan Wasit Juri.')
        ->and($hasil->keputusan_oleh)->toBe($this->ketua->id)
        ->and($hasil->status)->toBe(JurusBattle::STATUS_SELESAI);
});

it('menaikkan pemenang keputusan Ketua ke ronde berikutnya', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.70, 9.70);

    app(PutuskanBattle::class)(
        $battle,
        pemenangRegistrationId: $biru->registration_id,
        alasan: 'Keputusan Dewan.',
        oleh: $this->ketua,
    );

    $lanjut = $battle->bracket->battles()->where('round', 2)->sole();

    expect([$lanjut->red_registration_id, $lanjut->blue_registration_id])
        ->toContain($biru->registration_id);
});

it('menolak sudut yang bukan peserta battle ini', function () {
    [$battle] = ($this->battleDinilai)(9.70, 9.70);
    $asing = ($this->daftarkan)(1)->first();

    expect(fn () => app(PutuskanBattle::class)(
        $battle,
        pemenangRegistrationId: $asing->id,
        alasan: 'Keliru.',
    ))->toThrow(RuntimeException::class, 'bukan peserta battle ini');
});

/** Skor yang berbeda tetap diputus angka, tanpa campur tangan siapa pun. */
it('menetapkan pemenang dari skor tertinggi saat tidak seri', function () {
    [$battle, , $merah] = ($this->battleDinilai)(9.60, 9.75);

    $hasil = app(PutuskanBattle::class)($battle);

    expect($hasil->winner_registration_id)->toBe($merah->registration_id)
        ->and($hasil->win_reason)->toBe('angka')
        ->and($hasil->keputusan_alasan)->toBeNull();
});

/*
 * Pilihan manual atas battle yang TIDAK seri ditolak. Menerimanya berarti satu
 * tekanan tombol bisa membalikkan hasil yang sudah sah menurut angka, dan
 * tidak ada satu pun jejak yang membedakannya dari keputusan seri yang wajar.
 */
it('menolak pilihan manual yang bertentangan dengan skor yang tidak seri', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.60, 9.75);

    expect(fn () => app(PutuskanBattle::class)(
        $battle,
        pemenangRegistrationId: $biru->registration_id,
        alasan: 'Saya lebih suka biru.',
    ))->toThrow(RuntimeException::class, 'ditentukan angka, bukan dipilih');
});

it('menetapkan pemenang seri lewat rute, dan menolak juri biasa', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.70, 9.70);

    $juri = User::factory()->create();
    $juri->syncRoles(['juri']);

    $this->actingAs($juri)
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]), [
            'pemenang_registration_id' => $biru->registration_id,
            'alasan' => 'Keputusan Dewan.',
        ])
        ->assertForbidden();

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]), [
            'pemenang_registration_id' => $biru->registration_id,
            'alasan' => 'Keputusan Dewan.',
        ])
        ->assertSessionHasNoErrors();

    expect($battle->refresh()->winner_registration_id)->toBe($biru->registration_id);
});

it('menolak alasan kosong lewat rute', function () {
    [$battle, $biru] = ($this->battleDinilai)(9.70, 9.70);

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]), [
            'pemenang_registration_id' => $biru->registration_id,
        ])
        ->assertSessionHasErrors('alasan');
});

it('menolak sudut asing lewat rute', function () {
    [$battle] = ($this->battleDinilai)(9.70, 9.70);
    $asing = ($this->daftarkan)(1)->first();

    $this->actingAs($this->ketua)
        ->post(route('admin.turnamen.jurus.battle.putuskan', [$this->tournament, $battle]), [
            'pemenang_registration_id' => $asing->id,
            'alasan' => 'Keliru.',
        ])
        ->assertSessionHasErrors('pemenang_registration_id');
});
