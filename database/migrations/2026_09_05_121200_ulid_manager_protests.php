<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `manager_protests` pindah ke ULID, termasuk penunjuk ke dirinya
 * sendiri.
 *
 * Tabel ketiga belas, dan satu-satunya yang menunjuk tabelnya sendiri:
 * `parent_id` menghubungkan banding ke protes yang dibandingkan. Rantai itu
 * yang menyusun tingkatan protes manajer, dan kalau putus, banding kehilangan
 * protes asalnya -- keberatan yang tercatat tanpa perkara.
 *
 * Penunjuk yang menunjuk tabelnya sendiri tidak butuh perlakuan khusus di
 * sini, dan itu bukan kebetulan: helper memetakan id lama ke ULID lewat tabel
 * tujuan yang pada saat pemetaan masih memegang KEDUA kolom sekaligus. Yang
 * menunjuk dan yang ditunjuk kebetulan tabel yang sama, dan pemetaannya tetap
 * jalan.
 */
return new class extends Migration
{
    private const PENUNJUK = [
        ['manager_protests', 'parent_id'],
    ];

    public function up(): void
    {
        KonversiKunciUlid::keUlid('manager_protests', self::PENUNJUK);
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('manager_protests', self::PENUNJUK);
    }
};
