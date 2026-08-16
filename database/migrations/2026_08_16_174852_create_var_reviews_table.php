<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tinjauan VAR -- Pasal 15: pelatih mengangkat kartu meminta tinjauan
 * video atas keputusan wasit soal pelanggaran atau jatuhan, diputus Wasit
 * Komisi Protes dalam tenggat 5 menit.
 *
 * Rujukan ke nilai/hukuman yang disengketakan bersifat opsional -- protes
 * bisa juga soal keputusan yang TIDAK terjadi (mis. wasit tidak menghitung
 * jatuhan yang menurut pelatih sah), yang tidak punya baris score_events
 * atau penalties untuk dirujuk sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('var_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('protest_card_id')->constrained('protest_cards')->cascadeOnDelete();

            $table->unsignedSmallInteger('round');
            $table->string('corner', 8);
            $table->string('kejadian', 255);

            $table->foreignId('score_event_id')->nullable()->constrained('score_events')->nullOnDelete();
            $table->foreignId('penalty_id')->nullable()->constrained('penalties')->nullOnDelete();

            $table->timestamp('diajukan_at');
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tenggat_at');

            $table->timestamp('diputuskan_at')->nullable();
            $table->string('keputusan', 16)->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['match_id', 'diputuskan_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('var_reviews');
    }
};
