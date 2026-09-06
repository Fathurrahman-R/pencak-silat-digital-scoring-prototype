<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `judge_inputs` pindah ke ULID.
 *
 * Tabel keempat belas dan terakhir, sekaligus yang terbesar: satu baris tiap
 * penekanan tombol juri, sekitar seratus ribu baris per hari pertandingan
 * pada empat gelanggang.
 *
 * Tidak ditunjuk kolom mana pun -- `score_event_id` miliknya menunjuk KELUAR,
 * dan sudah lebih dulu berubah tipe bersama score_events.
 *
 * Ia tidak ikut sinkron peer-to-peer sama sekali; gelanggang tetangga tidak
 * berkepentingan atas penekanan tombol mentah gelanggang lain. Kuncinya tetap
 * harus pindah karena node global menampung arsip bukti dari empat gelanggang
 * sekaligus, dan empat penghitung auto-increment yang sama-sama mulai dari
 * satu akan bertabrakan di sana justru saat hasil pertandingan digugat.
 *
 * Ukuran itu yang membuatnya ditaruh terakhir: kalau cara konversinya keliru,
 * yang ketahuan lebih dulu adalah tiga belas tabel sebelumnya yang jauh lebih
 * murah diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('judge_inputs');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('judge_inputs');
    }
};
