<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format pertandingan satu nomor Jurus.
 *
 * Naskah Peraturan Pertandingan Pencak Silat Nasional 2025 menyebutnya
 * harfiah: "Pertandingan menggunakan Sistem Gugur" (Pasal 12.1.b.1), dan pada
 * bagian pemecah seri dua kali menegaskan "karena format Jurus sekarang
 * menggunakan sistem gugur". Gelanggang Jurus punya Sudut Merah dan Sudut Biru,
 * dan penampilan pertama dilakukan sudut biru lalu disusul sudut merah.
 *
 * Sistem ini dibangun dengan anggapan sebaliknya -- peserta tampil bergiliran
 * lalu diperingkat dari nilainya. Anggapan itu tertulis di komentar migrasi
 * jurus_performances dan di KategoriPertandingan::pakaiBagan(), dan keduanya
 * keliru terhadap naskah 2025.
 *
 * Bawaannya `penampilan` supaya nomor yang SUDAH tersusun di kejuaraan berjalan
 * tidak berubah bentuk di tengah jalan. Nomor baru dibuat sebagai `battle`;
 * yang menentukannya formulir, bukan kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurus_events', function (Blueprint $table) {
            $table->string('format', 16)->default('penampilan')->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('jurus_events', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
