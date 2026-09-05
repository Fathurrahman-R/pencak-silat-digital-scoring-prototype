<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angka partai yang sudah dihitung, disimpan supaya tidak dihitung ulang tiap
 * kali dibaca.
 *
 * Ini BUKAN pembatalan keputusan di TandingScoreCalculator:12-15. Kalkulator
 * tetap satu-satunya yang tahu cara menyusun skor, dan tetap sumber
 * kebenarannya. Yang ditambahkan di sini cuma tempat menaruh hasilnya, beserta
 * penanda kapan hasil itu dibuat.
 *
 * Yang membuatnya aman adalah `snapshot_pada` yang boleh kosong. Begitu satu
 * nilai atau hukuman berubah -- terbit, dibatalkan, atau dibatalkan
 * pembatalannya -- penanda itu dikosongkan, dan pembacaan berikutnya menghitung
 * ulang dari awal. Jadi koreksi dewan juri tetap berlaku seketika, persis
 * seperti sebelum kolom ini ada; yang hilang cuma menghitung ulang hal yang
 * sama berkali-kali di antara dua perubahan.
 *
 * Kenapa satu kolom json, bukan skor_merah dan skor_biru:
 * yang dibaca panel bukan cuma dua angka total, tapi juga skor tiap babak dan
 * rincian berapa kali tiap jenis serangan terbit. Memecahnya jadi kolom-kolom
 * berarti menambah kolom tiap kali panel menampilkan satu angka baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->json('snapshot_skor')->nullable()->after('ratified_by');

            // Presisi milidetik menyamai server_ts di score_events. Tanpa itu,
            // dua perubahan di detik yang sama tidak bisa dibedakan urutannya.
            $table->timestamp('snapshot_pada', 3)->nullable()->after('snapshot_skor');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['snapshot_skor', 'snapshot_pada']);
        });
    }
};
