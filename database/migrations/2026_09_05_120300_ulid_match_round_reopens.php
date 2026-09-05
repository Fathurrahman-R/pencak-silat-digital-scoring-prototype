<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `match_round_reopens` pindah ke ULID.
 *
 * Tabel keempat. Catatan pembukaan babak susulan: append-only, tidak pernah
 * dirujuk kolom lain, dan justru itu yang membuatnya aman dikonversi lebih
 * dulu daripada tabel yang menjadi tujuan foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('match_round_reopens');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('match_round_reopens');
    }
};
