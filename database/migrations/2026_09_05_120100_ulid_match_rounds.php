<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `match_rounds` pindah ke ULID.
 *
 * Tabel kedua. Seperti yang pertama, tidak ada kolom yang menunjuknya --
 * babak dirujuk lewat pasangan (match_id, round) yang sudah unik, bukan lewat
 * kunci utamanya. Unique itu tetap berlaku setelah perpindahan dan tetap
 * menjadi penjaga sebenarnya: dua baris babak ketiga untuk partai yang sama
 * tetap mustahil.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('match_rounds');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('match_rounds');
    }
};
