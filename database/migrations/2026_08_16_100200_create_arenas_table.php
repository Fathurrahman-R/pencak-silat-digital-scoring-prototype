<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gelanggang — Pasal 8.
 *
 * Satu kejuaraan lazim menjalankan beberapa gelanggang serentak, masing-masing
 * dengan wasit juri, operator, dan papan skornya sendiri. Gelanggang inilah
 * satuan yang dipakai kanal siaran langsung dan overlay vMix, bukan kejuaraan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Kode pendek yang muncul di papan skor dan URL siaran langsung.
            $table->string('code', 16);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tournament_id', 'code']);
            $table->index(['tournament_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arenas');
    }
};
