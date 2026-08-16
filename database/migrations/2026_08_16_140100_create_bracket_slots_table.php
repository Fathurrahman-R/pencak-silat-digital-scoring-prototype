<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat pada babak pertama bagan.
 *
 * Hanya babak pertama yang punya baris di sini; babak selanjutnya terisi
 * sendiri dari pemenang. Pemisahan ini yang membuat antarmuka undian
 * sederhana: menukar dua peserta berarti menukar isi dua baris, tanpa
 * menyentuh apa pun di babak berikutnya.
 *
 * Peserta yang kosong berarti bye — lawannya melaju tanpa bertanding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();

            // 1 sampai ukuran bagan, mengikuti urutan tempat baku.
            $table->unsignedSmallInteger('position');

            $table->foreignId('registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bracket_id', 'position']);

            // Satu peserta tidak boleh menempati dua tempat di bagan yang sama.
            $table->unique(['bracket_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_slots');
    }
};
