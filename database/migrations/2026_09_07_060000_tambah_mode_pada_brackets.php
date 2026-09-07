<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode penyusunan satu bagan Tanding: gugur (pangkat dua) atau pemasalan.
 *
 * Disimpan per bagan, bukan per kejuaraan: satu kejuaraan lazim memakai
 * pemasalan untuk golongan usia dini dan gugur untuk dewasa, di hari yang
 * sama. Bawaannya `gugur` supaya seluruh bagan yang sudah tersusun sebelum
 * kolom ini ada tetap terbaca persis seperti saat disusun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brackets', function (Blueprint $table) {
            $table->string('mode', 20)->default('gugur')->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('brackets', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
