<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal partai berhenti menyimpan jam.
 *
 * Pertandingan pencak silat berjalan menurut urutan tayang, bukan jam dinding:
 * partai molor karena protes, verifikasi juri, dan cedera, sehingga jam yang
 * dicetak pagi hari sudah meleset sebelum gelanggang kedua selesai babak
 * pertama. Jadwal yang jamnya meleset lebih menyesatkan daripada jadwal yang
 * tidak menyebut jam sama sekali.
 *
 * Kolomnya dihapus, bukan dibiarkan kosong. Kolom yang tidak pernah diisi tapi
 * masih terbaca akan dipakai lagi oleh kode baru dan menghidupkan kembali
 * asumsi jam yang justru sedang dibuang. Yang tersisa sebagai penentu urutan
 * adalah `order_in_arena`, dan yang menandai partai sedang dipertandingkan
 * adalah `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('order_in_arena');
        });
    }
};
