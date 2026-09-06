<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `jurus_scores` pindah ke ULID.
 *
 * Tabel kelima. Nilai juri untuk satu penampilan Jurus, dijaga
 * unique(performance_id, judge_user_id) -- satu juri satu nilai per
 * penampilan. Unique itu yang menjaga, bukan kunci utamanya, jadi
 * perpindahan ini tidak menyentuh aturannya sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('jurus_scores');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('jurus_scores');
    }
};
