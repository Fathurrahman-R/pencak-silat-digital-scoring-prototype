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
use App\Support\Bagan\PenjadwalPartai;

beforeEach(function () {
    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $this->kontingen = Contingent::factory()->for($this->tournament)->create();

    $this->arena1 = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 1']);
    $this->arena2 = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang 2']);

    $this->penjadwal = new PenjadwalPartai;

    /** Partai siap tanding: dua sudut terisi, belum selesai. */
    $this->buatPartai = function (string $kodeKelas, ?Athlete $merah = null, ?Athlete $biru = null): SilatMatch {
        $kelas = $this->tournament->weightClasses()
            ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', $kodeKelas)->firstOrFail();

        $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

        $regMerah = Registration::factory()->for($this->kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
        $regMerah->athletes()->attach($merah ?? Athlete::factory()->for($this->kontingen)->create());

        $regBiru = Registration::factory()->for($this->kontingen)->terverifikasi()
            ->create(['weight_class_id' => $kelas->id]);
        $regBiru->athletes()->attach($biru ?? Athlete::factory()->for($this->kontingen)->create());

        return SilatMatch::create([
            'bracket_id' => $bracket->id,
            'round' => 1,
            'position' => 1,
            'red_registration_id' => $regMerah->id,
            'blue_registration_id' => $regBiru->id,
            'status' => SilatMatch::STATUS_TERJADWAL,
        ]);
    };
});

it('menjadwalkan partai ke gelanggang dengan urutan tayang otomatis', function () {
    $partai = ($this->buatPartai)('C');

    $dijadwalkan = $this->penjadwal->tetapkan($partai, $this->arena1, now()->setTime(9, 0));

    expect($dijadwalkan->arena_id)->toBe($this->arena1->id)
        ->and($dijadwalkan->order_in_arena)->toBe(1)
        ->and($dijadwalkan->scheduled_at->format('H:i'))->toBe('09:00');
});

it('menambah urutan tayang di akhir antrean gelanggang yang sudah terisi', function () {
    $partaiSatu = ($this->buatPartai)('C');
    $partaiDua = ($this->buatPartai)('D');

    $this->penjadwal->tetapkan($partaiSatu, $this->arena1, now()->setTime(9, 0));
    $hasil = $this->penjadwal->tetapkan($partaiDua, $this->arena1, now()->setTime(10, 0));

    expect($hasil->order_in_arena)->toBe(2);
});

it('melepas jadwal partai', function () {
    $partai = ($this->buatPartai)('C');
    $this->penjadwal->tetapkan($partai, $this->arena1, now()->setTime(9, 0));

    $dilepas = $this->penjadwal->lepas($partai);

    expect($dilepas->arena_id)->toBeNull()
        ->and($dilepas->scheduled_at)->toBeNull()
        ->and($dilepas->order_in_arena)->toBeNull();
});

it('menolak menjadwalkan partai yang belum punya dua peserta', function () {
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();
    $bracket = Bracket::create(['weight_class_id' => $kelas->id, 'size' => 2]);

    $reg = Registration::factory()->for($this->kontingen)->terverifikasi()->create(['weight_class_id' => $kelas->id]);
    $reg->athletes()->attach(Athlete::factory()->for($this->kontingen)->create());

    $partai = SilatMatch::create([
        'bracket_id' => $bracket->id, 'round' => 1, 'position' => 1,
        'red_registration_id' => $reg->id, 'status' => SilatMatch::STATUS_TERJADWAL,
    ]);

    $this->penjadwal->tetapkan($partai, $this->arena1, now());
})->throws(RuntimeException::class, 'belum bisa dijadwalkan');

it('menolak menjadwalkan partai yang sudah selesai', function () {
    $partai = ($this->buatPartai)('C');
    $partai->update(['status' => SilatMatch::STATUS_SELESAI, 'winner_registration_id' => $partai->red_registration_id]);

    $this->penjadwal->tetapkan($partai, $this->arena1, now());
})->throws(RuntimeException::class, 'sudah selesai');

it('menolak menjadwalkan dua partai atlet yang sama ke gelanggang berbeda pada waktu berdekatan', function () {
    $budi = Athlete::factory()->for($this->kontingen)->create(['name' => 'Budi Santoso']);

    $partaiSatu = ($this->buatPartai)('C', merah: $budi);
    $partaiDua = ($this->buatPartai)('D', merah: $budi);

    $this->penjadwal->tetapkan($partaiSatu, $this->arena1, now()->setTime(9, 0));

    $this->penjadwal->tetapkan($partaiDua, $this->arena2, now()->setTime(9, 20));
})->throws(RuntimeException::class, 'Budi Santoso');

it('mengizinkan atlet yang sama dijadwalkan berdekatan di gelanggang yang sama', function () {
    $budi = Athlete::factory()->for($this->kontingen)->create(['name' => 'Budi Santoso']);

    $partaiSatu = ($this->buatPartai)('C', merah: $budi);
    $partaiDua = ($this->buatPartai)('D', merah: $budi);

    $this->penjadwal->tetapkan($partaiSatu, $this->arena1, now()->setTime(9, 0));
    $hasil = $this->penjadwal->tetapkan($partaiDua, $this->arena1, now()->setTime(9, 20));

    expect($hasil->arena_id)->toBe($this->arena1->id);
});

it('mengizinkan atlet yang sama dijadwalkan di gelanggang berbeda bila jaraknya cukup jauh', function () {
    $budi = Athlete::factory()->for($this->kontingen)->create(['name' => 'Budi Santoso']);

    $partaiSatu = ($this->buatPartai)('C', merah: $budi);
    $partaiDua = ($this->buatPartai)('D', merah: $budi);

    $this->penjadwal->tetapkan($partaiSatu, $this->arena1, now()->setTime(9, 0));
    $hasil = $this->penjadwal->tetapkan($partaiDua, $this->arena2, now()->setTime(10, 0));

    expect($hasil->arena_id)->toBe($this->arena2->id);
});

it('menukar urutan tayang dengan tetangganya', function () {
    $partaiSatu = ($this->buatPartai)('C');
    $partaiDua = ($this->buatPartai)('D');

    $this->penjadwal->tetapkan($partaiSatu, $this->arena1, now()->setTime(9, 0));
    $this->penjadwal->tetapkan($partaiDua, $this->arena1, now()->setTime(10, 0));

    $this->penjadwal->urutkan($partaiDua, -1);

    expect($partaiSatu->fresh()->order_in_arena)->toBe(2)
        ->and($partaiDua->fresh()->order_in_arena)->toBe(1);
});
