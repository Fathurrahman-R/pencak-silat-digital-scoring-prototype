<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Babak lama yang sedang dibuka untuk pencatatan susulan.
 *
 * Nilai atau hukuman yang terlewat di babak sebelumnya sampai sekarang tidak
 * bisa dicatat lagi: `current_round` hanya pernah naik, dan input untuk babak
 * bukan-aktif ditolak.
 *
 * Yang dibuat di sini BUKAN cara menurunkan `current_round`. Menurunkannya
 * berarti babak berikutnya kehilangan statusnya, dan skor yang sudah terbit di
 * sana menggantung tanpa babak yang memilikinya. Sebagai gantinya satu babak
 * lama "dibuka" untuk menerima susulan, sementara babak berjalan dijeda --
 * `current_round` tidak pernah bergerak mundur.
 *
 * Penandanya di `matches`, bukan `match_rounds`: CatatInputJuri sudah memegang
 * baris partai di tangannya, jadi pemeriksaannya nol query tambahan di jalur
 * terpanas sistem ini. Di `match_rounds` ia jadi satu query per tekanan tombol
 * juri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('susulan_round')->nullable()->after('current_round');
            $table->timestamp('susulan_dibuka_at')->nullable()->after('susulan_round');
            $table->foreignId('susulan_dibuka_oleh')->nullable()->after('susulan_dibuka_at')
                ->constrained('users')->nullOnDelete();

            /*
             * Apakah KAMI yang menjeda babak berjalan saat susulan dibuka.
             *
             * Menentukan apakah babak itu boleh dilanjutkan otomatis saat
             * susulan ditutup. Babak yang sudah dijeda pengendali sebelumnya --
             * karena cedera, karena protes -- tidak boleh ikut berjalan lagi
             * hanya karena susulan selesai dicatat.
             */
            $table->boolean('susulan_jeda_otomatis')->default(false)->after('susulan_dibuka_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('susulan_jeda_otomatis');
            $table->dropConstrainedForeignId('susulan_dibuka_oleh');
            $table->dropColumn(['susulan_dibuka_at', 'susulan_round']);
        });
    }
};
