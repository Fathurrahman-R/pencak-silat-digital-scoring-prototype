<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `technical_counts` pindah ke ULID.
 *
 * Tabel ketiga. Tidak ditunjuk kolom mana pun: hitungan teknik dibaca per
 * partai dan babak, tidak pernah dirujuk satu per satu dari tabel lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('technical_counts');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('technical_counts');
    }
};
