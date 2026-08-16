<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State timer babak, dipegang server -- bukan dihitung di browser manapun.
 *
 * Skor tiap babak sengaja tidak disimpan di sini. Ia dihitung ulang dari
 * score_events dan penalties setiap kali dibutuhkan (TandingScoreCalculator),
 * supaya tidak ada angka tersimpan yang bisa bertentangan dengan riwayat
 * penilaian yang sebenarnya -- pola yang sama dipakai golongan usia dan
 * posisi bagan berikutnya di modul lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedInteger('duration_ms');

            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('paused_at', 3)->nullable();
            $table->unsignedInteger('accumulated_ms')->default(0);

            $table->string('status', 16)->default('belum_mulai'); // belum_mulai | berjalan | jeda | selesai

            $table->timestamps();

            $table->unique(['match_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_rounds');
    }
};
