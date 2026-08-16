<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `current_round` menunjuk babak yang sedang aktif menerima input, dijaga
 * MatchTimer -- tanpa ini, tiap pengecekan "babak berapa yang berjalan
 * sekarang" harus menanyai match_rounds setiap saat.
 *
 * `ratified_at`/`ratified_by` menandai hasil sudah disahkan dewan juri.
 * Sebelum disahkan, `winner_registration_id` dan `win_reason` yang sudah ada
 * di tabel ini bersifat sementara -- bisa dikoreksi lewat panel dewan juri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_round')->nullable()->after('status');
            $table->timestamp('ratified_at')->nullable()->after('win_reason');
            $table->foreignId('ratified_by')->nullable()->after('ratified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ratified_by');
            $table->dropColumn(['current_round', 'ratified_at']);
        });
    }
};
