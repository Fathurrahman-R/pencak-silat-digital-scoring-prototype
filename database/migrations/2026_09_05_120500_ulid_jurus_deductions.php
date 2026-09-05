<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `jurus_deductions` pindah ke ULID.
 *
 * Tabel keenam. Pengurangan nilai Jurus -- Pasal 12.1.e. Tidak ditunjuk
 * kolom mana pun; pembatalannya lewat `voided_at` di baris ini sendiri,
 * bukan lewat tabel lain yang merujuknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('jurus_deductions');
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('jurus_deductions');
    }
};
