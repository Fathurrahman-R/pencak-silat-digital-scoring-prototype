<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak permanen: babak mana yang pernah dibuka ulang, oleh siapa, kapan.
 *
 * Kolom `susulan_*` di `matches` menyatakan keadaan SEKARANG dan dikosongkan
 * begitu susulan ditutup. Tabel ini menyimpan yang sudah lewat.
 *
 * Membuka kembali babak yang sudah ditutup adalah hal yang paling mungkin
 * digugat sesudah kejuaraan usai -- "nilai itu ditambahkan setelah babaknya
 * selesai" adalah keberatan yang wajar, dan jawabannya tidak boleh berupa
 * ingatan siapa pun.
 *
 * `score_events` dan `penalties` sengaja TIDAK mendapat kolom penanda.
 * Apakah sebuah nilai lahir saat susulan bisa diturunkan dari jendela
 * [opened_at, closed_at] terhadap `round`-nya -- gratis, append-only, dan
 * tidak menyentuh dua tabel terpanas sistem ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_round_reopens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('round');

            $table->foreignId('opened_by')->constrained('users');
            // Presisi milidetik: jendelanya dibandingkan dengan `server_ts`
            // score_events, yang juga bermilidetik.
            $table->timestamp('opened_at', 3);

            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at', 3)->nullable();

            $table->timestamps();

            $table->index(['match_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_round_reopens');
    }
};
