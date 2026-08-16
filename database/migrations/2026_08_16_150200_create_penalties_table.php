<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanksi wasit -- Pasal 11.6.d.4: pembinaan, teguran, peringatan.
 *
 * `level` adalah urutan sanksi itu dalam tahapnya sendiri (Teguran I = 1,
 * Teguran II = 2, dst); `points` adalah pengurangan nilai yang berlaku,
 * `null` untuk pembinaan (tidak mengurangi nilai) dan Peringatan III (berarti
 * diskualifikasi, bukan pengurangan). `violation_level` menyimpan sebab
 * pelanggarannya, bukan cuma akibatnya, karena wasit dan dewan juri perlu
 * melihat keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->string('corner', 4);

            $table->string('tier', 16); // pembinaan | teguran | peringatan
            $table->unsignedTinyInteger('level');
            $table->smallInteger('points')->nullable();
            $table->string('violation_level', 16); // ringan | sedang | berat
            $table->string('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->index(['match_id', 'corner', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
