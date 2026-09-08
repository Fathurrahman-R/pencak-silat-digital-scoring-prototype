<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toleransi waktu penampilan Jurus ikut pindah ke setelan kejuaraan.
 *
 * Dua angka per golongan usia: berapa detik kelebihan waktu yang masih
 * dimaafkan, dan pada kelebihan berapa detik penampilan dinyatakan gugur.
 * Keduanya sebelumnya hanya ada di `config/scoring.php`, sementara waktu acuan
 * tiap nomor sudah lama tersimpan per JurusEvent -- jadi separuh timer Jurus
 * bisa disetel panitia dan separuhnya lagi menuntut penyuntingan kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->json('jurus_waktu')->nullable()->after('hitungan_teknik');
        });

        DB::table('tournament_rule_settings')->update([
            'jurus_waktu' => json_encode([
                'toleransi_detik' => config('scoring.jurus.toleransi_detik'),
                'diskualifikasi_lewat_detik' => config('scoring.jurus.diskualifikasi_lewat_detik'),
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('tournament_rule_settings', function (Blueprint $table) {
            $table->dropColumn('jurus_waktu');
        });
    }
};
