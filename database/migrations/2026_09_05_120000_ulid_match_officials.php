<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `match_officials` pindah ke ULID.
 *
 * Tabel pertama dari empat belas, dan dipilih pertama karena tidak ada satu
 * pun kolom di basis data yang menunjuknya. Kalau ada yang salah dengan cara
 * konversinya, yang rusak hanya tabel ini -- bukan rantai relasi setengah
 * jadi yang menyeret tabel lain.
 *
 * Alasan seluruh perpindahan ini ada di App\Support\Sinkron\KonversiKunciUlid:
 * tiap gelanggang punya basis datanya sendiri, dan penghitung auto-increment
 * tiap basis data mulai dari satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('match_officials');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('match_officials');
    }
};
