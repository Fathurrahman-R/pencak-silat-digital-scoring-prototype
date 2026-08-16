<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai yang sudah sah karena mencapai ambang konsensus juri.
 *
 * Koreksi dewan juri tidak menyunting atau menghapus baris ini -- ia diberi
 * `voided_at` dan alasannya. Baris yang dibatalkan tetap tersimpan supaya
 * jejak penilaian tetap bisa ditelusuri utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->string('corner', 4);
            $table->string('point_type', 16);
            $table->unsignedTinyInteger('value');
            $table->timestamp('server_ts', 3);

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->index(['match_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_events');
    }
};
