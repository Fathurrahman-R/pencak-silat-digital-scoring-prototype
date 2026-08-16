<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atlet yang mengisi satu pendaftaran.
 *
 * Satu baris untuk kategori Tanding dan nomor tunggal, dua untuk Ganda, tiga
 * untuk Regu. Urutannya disimpan karena nomor beregu memanggil pesilatnya
 * berurutan saat penampilan dan pengumuman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_athlete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            // Satu atlet tidak masuk dua kali dalam satu pendaftaran.
            $table->unique(['registration_id', 'athlete_id']);
            $table->index('athlete_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_athlete');
    }
};
