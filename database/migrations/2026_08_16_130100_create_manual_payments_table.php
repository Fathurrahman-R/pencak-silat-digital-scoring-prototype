<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran di luar gerbang pembayaran: transfer bank, setor tunai di
 * sekretariat, atau pelunasan yang diurus antar panitia.
 *
 * Dibedakan tegas dari pembayaran gerbang karena tidak punya jejak pihak
 * ketiga sama sekali. Yang bisa dipertanggungjawabkan hanyalah bukti yang
 * diunggah bendahara dan namanya di jejak audit — dan justru karena itu
 * keduanya diwajibkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');

            // Keterangan wajib: nomor referensi transfer, nama penyetor, atau
            // sebab lain yang membuat pembayaran ini bisa ditelusuri kembali.
            $table->string('note');

            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();

            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payments');
    }
};
