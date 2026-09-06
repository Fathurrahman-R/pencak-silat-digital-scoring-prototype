<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `judge_verification_answers` pindah ke ULID.
 *
 * Tabel kesebelas. Tidak ditunjuk kolom mana pun -- jawaban dibaca lewat
 * verifikasinya, tidak pernah dirujuk satu per satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('judge_verification_answers');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('judge_verification_answers');
    }
};
