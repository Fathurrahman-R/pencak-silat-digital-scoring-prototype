<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akibat dari protes manajer yang DITERIMA -- Pasal 15 ayat 4 huruf c.e.
 *
 * Naskah memberi tiga bentuk jawaban, dan sampai sekarang sistem hanya
 * mencatat "diterima" tanpa menyebut yang mana:
 *
 *   1. Mengubah hasil secara langsung, bila ada unsur kesengajaan dan terbukti
 *      tenaga teknis melakukan pelanggaran.
 *   2. Menambah pertandingan 1 (satu) babak untuk kategori Tanding, bila ada
 *      kesalahan terbukti dan bukan kesengajaan.
 *   3. Menambah pertandingan dengan penampilan kembali untuk kategori Jurus,
 *      dalam keadaan yang sama.
 *
 * Protes yang diterima tanpa menyebut akibatnya adalah keputusan yang tidak
 * bisa dijalankan siapa pun: panitia tahu protesnya benar, tapi tidak tahu apa
 * yang harus terjadi berikutnya di gelanggang.
 *
 * `akibat_diterapkan_at` memisahkan "sudah diputuskan" dari "sudah dijalankan".
 * Keduanya berbeda, dan yang kedua yang membuka kunci pengesahan hasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_protests', function (Blueprint $table) {
            // 'ubah_hasil' | 'babak_tambahan' | 'penampilan_ulang'
            $table->string('akibat', 24)->nullable()->after('keputusan');
            $table->timestamp('akibat_diterapkan_at')->nullable()->after('akibat');
        });
    }

    public function down(): void
    {
        Schema::table('manager_protests', function (Blueprint $table) {
            $table->dropColumn(['akibat', 'akibat_diterapkan_at']);
        });
    }
};
