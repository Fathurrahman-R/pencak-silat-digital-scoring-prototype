<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai satu juri untuk satu penampilan -- skala 9.00-10.00, Pasal 12.1.f.
 *
 * Beda dari `judge_inputs` di Tanding, baris ini BOLEH ditimpa (upsert):
 * juri Jurus menulis satu angka akhir setelah menonton penampilan selesai,
 * bukan menekan tombol cepat berkali-kali dalam hitungan detik -- tidak ada
 * mekanisme konsensus real-time yang butuh jejak tiap perubahan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('jurus_performances')->cascadeOnDelete();
            $table->foreignId('judge_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('value', 4, 2);
            $table->timestamps();

            $table->unique(['performance_id', 'judge_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_scores');
    }
};
