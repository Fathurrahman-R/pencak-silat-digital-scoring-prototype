<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagan gugur tunggal untuk satu kelas tanding.
 *
 * Kategori Jurus tidak memakai bagan — pesertanya tampil bergiliran lalu
 * diperingkat dari nilainya, bukan saling menggugurkan. Karena itu bagan hanya
 * menunjuk kelas tanding.
 *
 * Setelah dikunci, susunan pesertanya tidak berubah lagi. Bagan yang bergeser
 * setelah diumumkan berarti kontingen menyiapkan lawan yang keliru, dan itu
 * jenis kesalahan yang tidak bisa diperbaiki di hari-H.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weight_class_id')->unique()->constrained()->cascadeOnDelete();

            // Selalu pangkat dua. Tempat yang tidak terisi peserta menjadi bye.
            $table->unsignedSmallInteger('size');

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brackets');
    }
};
