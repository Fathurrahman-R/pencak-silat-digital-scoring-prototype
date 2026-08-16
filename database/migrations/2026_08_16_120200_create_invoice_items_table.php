<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian tagihan.
 *
 * Keterangan baris disimpan sebagai teks, bukan dirakit ulang saat ditampilkan.
 * Kelas dan nomor boleh disunting atau dihapus panitia sesudahnya, sementara
 * tagihan yang sudah dibayar harus tetap bisa dibaca persis seperti saat
 * dibayar — itulah yang ditanyakan kalau ada sengketa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Kosong untuk biaya tetap kontingen, yang tidak menunjuk
            // pendaftaran mana pun.
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
