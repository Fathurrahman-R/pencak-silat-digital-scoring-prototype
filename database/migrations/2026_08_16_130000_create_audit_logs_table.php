<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak tindakan yang mengubah uang, skor, atau hasil pertandingan.
 *
 * Bukan log teknis. Isinya yang ditanyakan saat ada sengketa: siapa menandai
 * tagihan ini lunas padahal tidak ada catatan transfernya, siapa membatalkan
 * nilai di babak kedua, dan pukul berapa.
 *
 * Barisnya tidak pernah disunting atau dihapus. Sistem yang jejaknya bisa
 * diubah tidak menjawab pertanyaan apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Pelaku boleh kosong untuk tindakan yang dipicu sistem, misalnya
            // pekerjaan terjadwal yang mengembalikan tagihan kedaluwarsa.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 64);
            $table->nullableMorphs('auditable');
            $table->string('description');

            // Nilai sebelum dan sesudah, atau keterangan lain yang menjelaskan
            // tindakannya. Bentuknya berbeda-beda per jenis tindakan.
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
