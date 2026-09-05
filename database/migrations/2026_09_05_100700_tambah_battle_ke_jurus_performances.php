<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan penampilan Jurus ke battle dan sudutnya.
 *
 * Cara MENILAI tidak berubah sedikit pun: tetap satu baris `jurus_scores` per
 * juri per penampilan, skor akhir tetap median dikurangi pengurangan. Yang
 * ditambahkan hanya keterangan bahwa penampilan ini bagian dari sebuah battle,
 * dan berdiri di sudut mana.
 *
 * Keduanya nullable. Nomor berformat `penampilan` -- yang sudah berjalan
 * sebelum ini -- membiarkannya kosong, dan tidak satu baris lama pun perlu
 * disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurus_performances', function (Blueprint $table) {
            $table->foreignId('jurus_battle_id')->nullable()->after('jurus_event_id')
                ->constrained('jurus_battles')->cascadeOnDelete();

            // 'merah' atau 'biru'. Menentukan urutan tampil, bukan sekadar label:
            // naskah Pasal 12.1.d.7 menyuruh biru tampil lebih dulu.
            $table->string('sudut', 8)->nullable()->after('jurus_battle_id');
        });
    }

    public function down(): void
    {
        Schema::table('jurus_performances', function (Blueprint $table) {
            $table->dropColumn('sudut');
            $table->dropConstrainedForeignId('jurus_battle_id');
        });
    }
};
