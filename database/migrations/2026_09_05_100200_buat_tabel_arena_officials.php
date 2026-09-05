<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aparat yang bertugas di satu gelanggang sepanjang hari.
 *
 * Konsekuensi yang tidak bisa dihindari dari panel yang mengikuti gelanggang:
 * kalau juri duduk di gelanggang yang sama sepanjang hari, menugaskannya
 * PARTAI DEMI PARTAI lewat menu Aparat adalah beban yang persis sama besarnya
 * untuk sekretariat -- empat baris penugasan dikali empat puluh partai.
 *
 * `match_officials` TIDAK digantikan. Saat pengendali menunjuk sebuah partai,
 * baris di sini disalin ke sana. Alasannya keras:
 *
 *   - JudgeInputReceived::nomorJuri() memetakan judge_user_id ke nomor juri
 *     lewat match_officials;
 *   - pastikanAparatPartai() memakainya untuk otorisasi tiap tekanan tombol;
 *   - PollingVerifikasi menuntut penjawabnya tercatat sebagai juri partai itu;
 *   - berita acara mencetak nama petugas per partai.
 *
 * Menyalin sekali saat partai ditunjuk berarti keempat jalur itu tidak berubah
 * sama sekali, dan catatan siapa bertugas di partai mana tetap terekam PER
 * PARTAI -- bukan disimpulkan belakangan dari penugasan gelanggang yang bisa
 * berubah di tengah hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arena_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nilainya sama dengan match_officials.role: 'wasit', 'juri',
            // 'dewan-juri', 'komisi-protes', 'ketua-pertandingan'.
            $table->string('role', 32);

            // Juri 1, 2, 3. Kosong untuk peran yang tidak bernomor.
            $table->unsignedTinyInteger('number')->nullable();
            $table->timestamps();

            // Satu orang satu peran per gelanggang.
            $table->unique(['arena_id', 'user_id']);
            $table->unique(['arena_id', 'role', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_officials');
    }
};
