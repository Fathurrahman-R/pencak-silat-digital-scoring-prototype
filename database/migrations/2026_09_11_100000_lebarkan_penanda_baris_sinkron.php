<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `sinkron_keluar.baris_id` dilebarkan dari 64 jadi 255 karakter.
 *
 * Kolom ini dibuat sepanjang ULID (26 karakter) ditambah kelonggaran, dan itu
 * cukup selama tiap baris punya satu kolom kunci. Tiga tabel pivot Spatie
 * tidak punya `id` sama sekali, jadi penandanya kini berupa kunci gabungan
 * dalam bentuk JSON:
 *
 *     {"permission_id":"21","model_type":"App\\Models\\Role","model_id":"2"}
 *
 * Delapan puluh sekian karakter, dan MySQL memotongnya dengan galat
 * "Data too long" tepat saat peran pertama dipasang.
 *
 * 255, bukan TEXT: kolom ini ikut dalam pemadatan per (tabel, baris_id) dan
 * layak tetap bisa diindeks kalau nanti dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sinkron_keluar', function (Blueprint $table) {
            $table->string('baris_id', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sinkron_keluar', function (Blueprint $table) {
            $table->string('baris_id', 64)->change();
        });
    }
};
