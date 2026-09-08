<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ambang hitungan teknik ikut pindah ke setelan kejuaraan.
 *
 * Sebelum ini ketiga angkanya -- hitungan yang menerbitkan Teguran, hitungan
 * yang berarti menang mutlak, dan berapa hitungan beruntun yang berarti menang
 * teknik -- hanya ada di `config/scoring.php`. Mengubahnya untuk satu kejuaraan
 * berarti menyunting berkas kode di lima laptop, dan perubahannya ikut mengenai
 * kejuaraan lain yang kebetulan ada di basis data yang sama.
 *
 * Kolomnya JSON, seperti `hukuman` dan `babak`, karena isinya sekelompok angka
 * yang selalu dibaca bersama dan tidak pernah dicari satu per satu lewat WHERE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->json('hitungan_teknik')->nullable()->after('babak');
        });

        /*
         * Baris yang sudah ada diisi angka naskah, bukan dibiarkan null.
         *
         * Null akan dibaca kode sebagai "pakai config", dan itu berarti
         * kejuaraan yang berjalan diam-diam kembali bergantung pada berkas yang
         * justru sedang ditinggalkan. Diisi sekarang, seluruh kejuaraan memegang
         * salinannya sendiri sejak menit pertama.
         */
        DB::table('tournament_rule_settings')->update([
            'hitungan_teknik' => json_encode(config('scoring.tanding.hitungan_teknik')),
        ]);
    }

    public function down(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->dropColumn('hitungan_teknik');
        });
    }
};
