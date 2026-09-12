<?php

/*
 * Pembantu data untuk k5-promosi-lintas.mjs.
 *
 * Dijalankan dengan `php artisan tinker scripts/qa/k5-data.php`, parameternya
 * lewat env. Bukan lewat `--execute`: di Windows, perintah PHP yang panjang
 * harus melewati cmd.exe milik Node, dan tanda kutip di dalamnya luruh sampai
 * perintahnya gagal tanpa pesan yang berguna. Berkas tidak punya masalah itu.
 *
 * Mencetak satu baris JSON, dan hanya itu.
 */

use App\Models\Athlete;
use App\Models\Registration;
use App\Models\SilatMatch;

$aksi = (string) getenv('QA_AKSI');
$kelas = (int) getenv('QA_KELAS');
$kontingen = (int) getenv('QA_KONTINGEN');
$partai = (int) getenv('QA_PARTAI');

$hasil = match ($aksi) {
    'partai' => SilatMatch::query()
        ->whereHas('bracket', fn ($q) => $q->where('weight_class_id', $kelas))
        ->orderBy('round')->orderBy('position')
        ->pluck('id')->all(),

    'keadaan' => (function () use ($partai) {
        $p = SilatMatch::findOrFail($partai);

        return [
            'arena_id' => $p->arena_id,
            'red' => $p->red_registration_id,
            'blue' => $p->blue_registration_id,
            'status' => $p->status,
        ];
    })(),

    'susulan' => (function () use ($kontingen) {
        /*
         * Kelasnya sendiri, bukan kelas yang baganya sedang diuji: pendaftaran
         * kelima membuat bagan berikutnya berukuran delapan, dan seluruh
         * kasus sesudahnya rontok karena alasan yang tidak ada hubungannya
         * dengan yang sedang diuji.
         */
        $kelas = App\Models\WeightClass::firstOrCreate(
            ['tournament_id' => 1, 'code' => 'QS'],
            [
                'golongan_usia' => 'dewasa',
                'jenis_kelamin' => 'putra',
                'name' => 'Kelas QA Susulan',
                'weight_min' => 60.0,
                'weight_max' => 70.0,
                'weight_min_exclusive' => false,
                'weight_max_inclusive' => true,
                'sort_order' => 98,
                'is_active' => true,
            ],
        );

        $atlet = Athlete::create([
            'contingent_id' => $kontingen,
            'name' => 'Pesilat QA Susulan',
            'jenis_kelamin' => 'putra',
            'birth_date' => '2000-05-05',
            'weight_claim' => 55.0,
        ]);

        $daftar = Registration::create([
            'contingent_id' => $kontingen,
            'weight_class_id' => $kelas->id,
            'status' => 'terverifikasi',
            'verified_by' => 4,
            'verified_at' => now(),
            'submitted_at' => now(),
        ]);

        $daftar->athletes()->attach($atlet, ['position' => 1]);

        return ['atlet' => $atlet->id, 'pendaftaran' => $daftar->id];
    })(),

    'bersihkan' => (function () use ($kelas) {
        $bagan = App\Models\Bracket::where('weight_class_id', $kelas)->first();

        if ($bagan === null) {
            return ['dibuang' => false];
        }

        /*
         * Satu per satu lewat model, bukan penghapusan massal: penghapusan
         * massal tidak menembakkan observer, jadi node gelanggang tidak
         * pernah diberi tahu dan menyimpan partai dari bagan yang sudah tidak
         * ada di node global.
         */
        SilatMatch::where('bracket_id', $bagan->id)->get()->each->delete();
        App\Models\BracketSlot::where('bracket_id', $bagan->id)->get()->each->delete();
        $bagan->delete();

        return ['dibuang' => true];
    })(),

    default => throw new RuntimeException("QA_AKSI tidak dikenal: {$aksi}"),
};

echo 'HASIL='.json_encode($hasil).PHP_EOL;
