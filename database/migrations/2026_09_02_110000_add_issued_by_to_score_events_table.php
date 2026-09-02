<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa yang menerbitkan satu nilai, bila bukan juri.
 *
 * Nilai biasa lahir dari tombol juri: penerbitnya terbaca dari judge_inputs
 * yang menyusunnya. Nilai mutlak jatuhan tidak punya baris itu -- ia keputusan
 * Dewan Wasit Juri, bukan hasil konsensus tiga juri -- dan tanpa kolom ini
 * riwayat maupun berita acara menampilkan jatuhan +3 yang seolah muncul tanpa
 * satu pun penekan. Justru baris itulah yang paling dipersoalkan saat hasilnya
 * digugat.
 *
 * Kosong berarti nilainya memang lahir dari tombol juri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_events', function (Blueprint $table) {
            $table->foreignId('issued_by')->nullable()->after('server_ts')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('score_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
        });
    }
};
