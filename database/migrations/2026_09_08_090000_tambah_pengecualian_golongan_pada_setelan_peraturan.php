<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian setelan per golongan usia.
 *
 * Setelan peraturan sampai sekarang berlaku satu angka untuk seluruh
 * kejuaraan: satu cakupan teguran, satu ambang hitungan teknik. Praktiknya
 * tidak selalu begitu -- penyelenggara yang menghitung teguran sepanjang
 * partai untuk Dewasa sering tetap mereset tiap babak untuk Usia Dini, dan
 * hitungan beruntun terhadap pesilat yang jatuh diperlakukan berbeda antar
 * golongan. Sebelum ini satu-satunya jalan mengikutinya adalah menyunting
 * `config/scoring.php`, yang berarti seluruh golongan ikut berubah.
 *
 * Isinya PARTIAL, bukan salinan penuh: hanya kunci yang benar-benar
 * dikecualikan yang tersimpan, sisanya jatuh kembali ke setelan umum. Salinan
 * penuh per golongan berarti setelan umum yang diubah panitia diam-diam tidak
 * berlaku di golongan mana pun, dan tidak ada yang menyadarinya sampai partai
 * pertama golongan itu berjalan.
 *
 * Bentuknya:
 *
 *   {
 *     "dewasa": {
 *       "hukuman": { "teguran": { "cakupan": "partai" } },
 *       "hitungan_teknik": { "cakupan_beruntun": "partai", "teguran_pada_hitungan": 8 }
 *     }
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->json('override_golongan')->nullable()->after('jurus_waktu');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->dropColumn('override_golongan');
        });
    }
};
