<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tagihan per kontingen per kejuaraan.
 *
 * Nomor tagihan dipakai sebagai dasar `order_id` di gerbang pembayaran, jadi
 * unik lintas kejuaraan — bukan hanya di dalam satu kejuaraan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingent_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status')->default('draf');
            $table->unsignedInteger('total_amount')->default(0);

            // Saat nominal dibekukan; sejak titik ini isinya tidak lagi ikut
            // berubah mengikuti pendaftaran.
            $table->timestamp('locked_at')->nullable();

            $table->timestamp('paid_at')->nullable();

            // 'midtrans' atau 'manual'. Pembayaran manual dibedakan tegas
            // karena tidak punya jejak di gerbang pembayaran dan hanya bisa
            // dipertanggungjawabkan lewat bukti yang diunggah bendahara.
            $table->string('paid_via', 32)->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
