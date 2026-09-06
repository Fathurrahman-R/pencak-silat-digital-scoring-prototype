<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `var_reviews` pindah ke ULID.
 *
 * Tabel kedua belas. Tinjauan VAR menunjuk banyak hal -- kartu protes, nilai,
 * hukuman -- tapi tidak ada yang menunjuk dia. Ketiga kolom penunjuknya sudah
 * lebih dulu berubah tipe bersama tabel tujuannya masing-masing.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('var_reviews');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('var_reviews');
    }
};
