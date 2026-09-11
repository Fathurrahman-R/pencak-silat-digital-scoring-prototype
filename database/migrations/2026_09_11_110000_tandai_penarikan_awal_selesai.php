<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Menandai kapan sebuah peer pernah ditarik SAMPAI HABIS.
 *
 * Halaman pemasangan node harus hidup sampai penarikan awal selesai, bukan
 * sampai baris pertama masuk. Tanpa penanda ini tidak ada cara tahu bedanya:
 * kursor yang maju sama saja bentuknya, entah baru seperempat jalan atau sudah
 * di ujung.
 *
 * Terukur 11 September 2026: potongan pertama membawa akun beserta perannya,
 * halaman pemasangan langsung menutup diri, dan sisa dua belas ribu baris
 * tidak pernah bisa ditarik dari mana pun -- akun sudah ada, tapi kejuaraannya
 * belum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sinkron_kursor', function (Blueprint $table) {
            $table->timestamp('selesai_pada')->nullable()->after('ditarik_pada');
        });
    }

    public function down(): void
    {
        Schema::table('sinkron_kursor', function (Blueprint $table) {
            $table->dropColumn('selesai_pada');
        });
    }
};
