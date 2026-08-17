<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengurangan nilai -- Pasal 12.1.e. Dua tingkat dengan pencatat dan besaran
 * berbeda: 0.01 oleh juri (kesalahan gerak/urutan), 0.50 oleh Pengawas/Dewan
 * Wasit Juri (pelanggaran waktu, keluar gelanggang, dan sejenisnya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('jurus_performances')->cascadeOnDelete();
            $table->string('tier', 16); // juri | pengawas
            $table->string('alasan', 255);
            $table->decimal('jumlah', 4, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_deductions');
    }
};
