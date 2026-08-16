<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekaman mentah tiap penekanan tombol juri. Baris di sini tidak pernah
 * disunting atau dihapus -- satu-satunya perubahan yang boleh terjadi adalah
 * mengisi `score_event_id` sekali, saat input itu ikut membentuk nilai.
 *
 * `score_event_id` sengaja tanpa constraint foreign key: tabel score_events
 * baru ada di migrasi berikutnya, dan menambah constraint melingkar di sini
 * hanya menambah kerumitan urutan migrasi tanpa manfaat -- keterkaitannya
 * sudah cukup dijaga di ConsensusEvaluator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');

            $table->foreignId('judge_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('corner', 4); // red | blue
            $table->string('point_type', 16); // pukulan | tendangan | jatuhan

            // Stempel waktu resmi, dibubuhkan server saat input tiba -- jam
            // perangkat juri tidak pernah dipercaya.
            $table->timestamp('server_ts', 3);
            $table->timestamp('client_ts', 3)->nullable();

            $table->unsignedBigInteger('score_event_id')->nullable();
            $table->string('rejected_reason')->nullable();

            $table->timestamps();

            $table->index(['match_id', 'round', 'corner', 'point_type', 'server_ts'], 'judge_inputs_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_inputs');
    }
};
