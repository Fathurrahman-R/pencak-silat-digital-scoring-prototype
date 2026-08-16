<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontingen: satu tim peserta di satu kejuaraan.
 *
 * Terikat pada kejuaraan, bukan berdiri sendiri lintas kejuaraan. Perguruan
 * yang sama bisa mengirim susunan atlet dan official yang sepenuhnya berbeda
 * dari tahun ke tahun, dan menyatukannya berarti mengubah data tahun lalu
 * setiap kali data tahun ini diperbaiki.
 *
 * Satu invoice terbit per kontingen per kejuaraan, jadi tabel inilah satuan
 * penagihannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contingents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            // Official yang mengelola kontingen ini. Nullable karena panitia
            // kadang mendaftarkan kontingen lebih dulu, baru akunnya menyusul.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('region')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tournament_id', 'name']);
            $table->index(['tournament_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contingents');
    }
};
