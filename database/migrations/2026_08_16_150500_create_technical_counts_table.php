<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per insiden hitungan wasit -- Pasal 11.6.g.2/3. `corner` adalah
 * pesilat yang dihitung, `count_reached` adalah hitungan tertinggi sebelum ia
 * bangkit atau wasit berhenti menghitung (1-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->string('corner', 4);
            $table->unsignedTinyInteger('count_reached');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['match_id', 'round', 'corner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_counts');
    }
};
