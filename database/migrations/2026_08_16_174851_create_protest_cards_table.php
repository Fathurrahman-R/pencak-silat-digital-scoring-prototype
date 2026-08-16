<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jatah kartu protes pelatih -- Pasal 15.2.a: 2 kartu per pertandingan
 * Tanding, berlaku sepanjang tiga babak (satu baris per sudut per partai,
 * bukan per babak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protest_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('corner', 8);
            $table->unsignedTinyInteger('jumlah_dipakai')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'corner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protest_cards');
    }
};
