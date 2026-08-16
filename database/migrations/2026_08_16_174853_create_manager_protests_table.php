<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protes Manajer -- Pasal 15 ayat 4. Berjenjang: tingkat pertama ke Ketua
 * Pertandingan, banding ke Delegasi Teknik lewat `parent_id`. Keputusan
 * banding bersifat final.
 *
 * Tenggatnya diturunkan dari `config('scoring.protes_manajer')` saat
 * diajukan, bukan dihitung ulang tiap tampil -- supaya perubahan konfigurasi
 * di kemudian hari tidak diam-diam mengubah tenggat protes yang sudah
 * berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_protests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('level', 16);
            $table->foreignId('parent_id')->nullable()->constrained('manager_protests')->nullOnDelete();

            $table->timestamp('diajukan_at');
            $table->timestamp('tenggat_formulir_at');
            $table->timestamp('tenggat_keputusan_at');
            $table->timestamp('formulir_dikembalikan_at')->nullable();

            $table->timestamp('diputuskan_at')->nullable();
            $table->string('keputusan', 16)->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->unique(['match_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_protests');
    }
};
