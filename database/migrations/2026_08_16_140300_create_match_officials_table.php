<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan aparat per partai — Pasal 16.
 *
 * Nomor urut dipakai membedakan Juri 1, Juri 2, dan Juri 3. Itu bukan hiasan:
 * berita acara dan panel dewan juri menyebut juri berdasarkan nomornya, dan
 * saat ada sengketa yang ditanyakan adalah nomor berapa yang menekan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'wasit', 'juri', 'ketua-pertandingan', 'pengawas'.
            $table->string('role', 32);

            $table->unsignedTinyInteger('number')->nullable();
            $table->timestamps();

            // Satu orang satu peran per partai. Wasit tidak merangkap juri.
            $table->unique(['match_id', 'user_id']);
            $table->unique(['match_id', 'role', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_officials');
    }
};
