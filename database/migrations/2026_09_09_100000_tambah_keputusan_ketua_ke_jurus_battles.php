<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Ketua Pertandingan untuk battle yang skor akhirnya SAMA.
 *
 * Sebelum ini seri dipecah sistem sendiri lewat rantai peringkat: hukuman
 * terendah, waktu terdekat ke acuan, standar deviasi -- lalu undian. Rantai itu
 * aturan peringkat nomor berformat penampilan, dan ujungnya undian: seorang
 * pesilat tersingkir dari bagan gugur oleh angka acak yang tidak pernah
 * diumumkan kepada siapa pun, dan yang tidak bisa dijelaskan kepada pelatih
 * yang menanyakannya.
 *
 * Di battle, yang memutuskan Ketua Pertandingan. Alasannya disimpan sebagai
 * kolom, bukan hanya sebagai jejak audit: ia ikut tercetak di berita acara,
 * dan berita acara tidak membaca `audit_logs`.
 *
 * Dua kolom nullable tanpa nilai bawaan yang harus diisi -- aman dijalankan di
 * basis data berisi, tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurus_battles', function (Blueprint $table) {
            $table->string('keputusan_alasan', 255)->nullable()->after('win_reason');
            $table->foreignId('keputusan_oleh')->nullable()->after('keputusan_alasan')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jurus_battles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('keputusan_oleh');
            $table->dropColumn('keputusan_alasan');
        });
    }
};
