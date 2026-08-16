<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atlet, milik satu kontingen.
 *
 * Golongan usia tidak disimpan sebagai kolom. Ia dihitung dari tanggal lahir
 * terhadap tanggal kejuaraan dimulai — kolom tersimpan akan menjadi salah
 * begitu jadwal kejuaraan digeser, dan salahnya diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingent_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('jenis_kelamin');
            $table->date('birth_date');

            /*
             * Berat yang diakui kontingen saat mendaftar, dipakai memilih
             * kelas. Yang menentukan pada akhirnya adalah hasil timbang badan
             * di venue — lihat weight_ins.
             */
            $table->decimal('weight_claim', 5, 1)->nullable();

            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contingent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
