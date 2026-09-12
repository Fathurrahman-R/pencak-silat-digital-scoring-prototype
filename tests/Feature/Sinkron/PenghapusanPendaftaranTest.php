<?php

use App\Actions\Turnamen\SusunMasterDataTurnamen;
use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Athlete;
use App\Models\Contingent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PenerapPaket;
use Illuminate\Support\Facades\DB;

/*
 * Pendaftaran yang dibatalkan di node global.
 *
 * Penerapan paket mematikan pemeriksaan foreign key selama satu potongan --
 * induk sebuah baris bisa berada di potongan lain, dan satu pelanggaran
 * menghentikan seluruh penarikan. Tapi foreign key yang mati juga berarti
 * penghapusan tidak merambat: baris pivot pendaftaran-atlet bisa tertinggal
 * menunjuk pendaftaran yang sudah tidak ada.
 *
 * Yang diuji di sini: sesudah pendaftaran dihapus lewat sinkron, tidak ada
 * baris pivot yatim yang tersisa di node gelanggang.
 */

beforeEach(function () {
    config([
        'sinkron.peran' => 'gelanggang',
        'sinkron.arena' => 'B',
        'sinkron.node' => 'gelanggang-b',
    ]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    (new SusunMasterDataTurnamen)($this->tournament);

    $kontingen = Contingent::factory()->for($this->tournament)->create();
    $kelas = $this->tournament->weightClasses()
        ->untuk(GolonganUsia::Dewasa, JenisKelamin::Putra)->where('code', 'C')->firstOrFail();

    $this->pendaftaran = Registration::factory()->for($kontingen)->terverifikasi()
        ->create(['weight_class_id' => $kelas->id]);

    $this->pendaftaran->athletes()->attach(
        Athlete::factory()->for($kontingen)->create(),
        ['position' => 1],
    );
});

it('tidak meninggalkan baris pivot yatim saat pendaftaran dihapus lewat sinkron', function () {
    $ringkasan = (new PenerapPaket(new Kepemilikan))->terapkan([
        'baris' => [[
            'tabel' => 'registrations',
            'id' => (string) $this->pendaftaran->id,
            'aksi' => CatatanKeluar::HAPUS,
        ]],
    ]);

    $yatim = DB::table('registration_athlete')
        ->where('registration_id', $this->pendaftaran->id)
        ->count();

    expect($ringkasan['dihapus'])->toBe(1)
        ->and($yatim)->toBe(0);
});
