<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `penalties` pindah ke ULID, beserta dua kolom yang menunjuknya.
 *
 * Tabel kesembilan. Hukuman dirujuk dari dua arah, dan keduanya justru dari
 * jalur koreksi: verifikasi juri yang meninjau ulang sebuah hukuman, dan
 * tinjauan VAR yang mempersoalkannya. Keduanya harus tetap menemukan
 * hukumannya setelah perpindahan -- itulah jalur yang dibuka saat keputusan
 * wasit digugat.
 */
return new class extends Migration
{
    private const PENUNJUK = [
        ['judge_verifications', 'penalty_id'],
        ['var_reviews', 'penalty_id'],
    ];

    public function up(): void
    {
        KonversiKunciUlid::keUlid('penalties', self::PENUNJUK);
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('penalties', self::PENUNJUK);
    }
};
