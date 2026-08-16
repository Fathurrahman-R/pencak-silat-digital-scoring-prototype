<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil timbang badan — Pasal 2 ayat 4.
 *
 * Penimbangan ulang dicatat sebagai baris baru, tidak pernah menimpa baris
 * sebelumnya. Timbang badan adalah dasar seorang atlet boleh atau tidak boleh
 * bertanding, dan kalau ada sengketa, yang ditanyakan justru berapa hasil
 * penimbangan pertama dan siapa yang menimbangnya.
 *
 * Pra Usia Dini dan Usia Dini 1 tidak menjalani timbang badan sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();

            $table->decimal('weight', 5, 1);

            // Hasilnya ditetapkan saat penimbangan terhadap kelas yang berlaku
            // waktu itu, bukan dihitung ulang belakangan — kelas boleh saja
            // disunting panitia sesudahnya, dan hasil yang sudah ditandatangani
            // tidak ikut berubah karenanya.
            $table->boolean('passed');

            $table->timestamp('weighed_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'weighed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_ins');
    }
};
