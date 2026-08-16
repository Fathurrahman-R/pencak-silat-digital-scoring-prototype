<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan parameter peraturan milik satu kejuaraan.
 *
 * Isinya berasal dari config/scoring.php saat kejuaraan dibuat, lalu berdiri
 * sendiri. Ini disengaja: kalau mesin scoring membaca langsung dari berkas
 * konfigurasi, menyunting berkas itu akan mengubah dasar perhitungan partai
 * yang sudah terlanjur dinilai di kejuaraan yang sedang berjalan — termasuk
 * partai yang hasilnya sudah disahkan.
 *
 * Parameter yang dibaca mesin scoring pada jalur panas diberi kolomnya
 * sendiri supaya bisa diindeks dan divalidasi. Sisanya, yang berbentuk tabel
 * bertingkat dan hanya dibaca utuh, disimpan sebagai JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_rule_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->unique()->constrained()->cascadeOnDelete();

            /*
             * Komposisi juri — Pasal 16 ayat 1.
             *
             * Ambang sepakat dan lebar jendela TIDAK diatur naskah. Naskah
             * hanya menetapkan jumlah jurinya. Keduanya keputusan implementasi,
             * dan justru karena itu dibuat bisa diatur per kejuaraan.
             */
            $table->unsignedTinyInteger('jumlah_juri_tanding')->default(3);
            $table->unsignedTinyInteger('ambang_sepakat')->default(2);
            $table->unsignedSmallInteger('window_konsensus_ms')->default(2000);
            $table->unsignedTinyInteger('jumlah_juri_jurus')->default(6);

            // Istirahat antar babak — Pasal 11 ayat 3. Jumlah dan durasi babak
            // berbeda per golongan usia, jadi ada di dalam `babak`.
            $table->unsignedInteger('istirahat_ms')->default(60000);

            /*
             * Tabel bertingkat, selalu dibaca utuh:
             *   nilai       jenis serangan => angka (Pasal 11.6.e)
             *   hukuman     tangga pembinaan/teguran/peringatan (Pasal 11.6.d.4)
             *   babak       golongan usia => jumlah dan durasi (Pasal 11 ayat 3)
             *   wmp_selisih ambang menang mutlak per golongan (Pasal 11.6.g.4.b)
             */
            $table->json('nilai');
            $table->json('hukuman');
            $table->json('babak');
            $table->json('wmp_selisih');

            // Kartu protes dan tenggat VAR — Pasal 15.
            $table->unsignedTinyInteger('kartu_protes_tanding')->default(2);
            $table->unsignedTinyInteger('kartu_protes_jurus')->default(1);
            $table->unsignedSmallInteger('tenggat_var_detik')->default(300);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_rule_settings');
    }
};
