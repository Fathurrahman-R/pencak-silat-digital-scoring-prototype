<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengendali yang memegang satu gelanggang.
 *
 * Tabel sendiri, bukan kolom `role` di `arena_operators`. Menyisipkan kolom ke
 * sana berarti membongkar `unique(arena_id, user_id)` yang sudah ada, dan
 * `Arena::operators()` beserta ArenaController::simpanOperator() ikut harus
 * dibedah. Tabel terpisah menyentuh nol baris kode yang sudah bekerja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_pengendali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arena_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['arena_id', 'user_id']);
        });

        $this->salinDariOperator();
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_pengendali');
    }

    /**
     * Setiap operator yang sudah memegang gelanggang ikut dicatat sebagai
     * pengendalinya.
     *
     * Tanpa ini, hari kode ini terpasang tidak ada satu akun pun yang boleh
     * menjalankan timer -- peran `operator-it` baru saja kehilangan wewenang
     * itu, dan `pengendali-gelanggang` belum dipegang siapa pun. Gelanggang
     * lumpuh sampai panitia menugaskan ulang satu per satu.
     *
     * Penyempitan siapa yang benar-benar jadi pengendali diserahkan ke panitia
     * lewat halaman Gelanggang. Ini hanya menjaga hari-H tidak berhenti.
     */
    private function salinDariOperator(): void
    {
        if (! Schema::hasTable('arena_operators')) {
            return;
        }

        $baris = DB::table('arena_operators')
            ->select('arena_id', 'user_id', 'created_at', 'updated_at')
            ->get()
            ->map(fn ($b) => (array) $b)
            ->all();

        if ($baris === []) {
            return;
        }

        DB::table('arena_pengendali')->insert($baris);
    }
};
