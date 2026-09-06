<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua index yang hilang di jalur yang ditarik paling sering.
 *
 * Keduanya dipilih dengan mengukur, bukan menebak: seratus ribu baris
 * judge_inputs disusun di basis data terpisah lewat `silat:beban`, lalu tiap
 * calon index dipasang dan kuerinya dijalankan ulang. Yang tidak memperbaiki
 * angka tidak ikut ke sini.
 *
 *   judge_inputs.score_event_id
 *     Panel memuat riwayat tiga puluh nilai terakhir beserta penekan
 *     tombolnya, dan tanpa index MySQL memindai seluruh tabel untuk mencari
 *     tiga puluh baris. Pada seratus ribu baris: 51,58 ms turun ke 0,73 ms,
 *     dan baris yang dibaca turun dari 100.159 menjadi 30. Kuerinya berjalan
 *     tiap panel menyegarkan diri, di tiap panel yang terbuka.
 *
 *   matches.ratified_at
 *     Beranda mengurutkan hasil terakhir dengan kolom ini. Tanpa index ia
 *     memindai seluruh partai turnamen lalu menyortirnya untuk mengambil enam.
 *
 * Yang SUDAH diukur dan sengaja TIDAK ditambahkan, supaya tidak dicoba lagi:
 *
 *   score_events (match_id, voided_at, round, corner, value) dan
 *   (match_id, voided_at, corner, point_type)
 *     Terlihat menggoda karena rekapSkor() dan rekapTeknik() memang memakai
 *     GROUP BY dan EXPLAIN memang menyebut "Using temporary". Tapi index
 *     (match_id, round) yang sudah ada lebih dulu menyempitkan ke lima puluh
 *     enam baris -- satu partai memang cuma punya sebanyak itu nilai --
 *     dan tabel sementara di atas lima puluh enam baris tidak berbiaya apa
 *     pun. Perbaikan terukurnya nol, sementara ongkosnya nyata: dua index
 *     yang harus ikut diperbarui tiap nilai terbit, di jalur terpanas sistem.
 *
 *   penalties (match_id, voided_at, round, corner, points)
 *     Alasan sama. Satu partai punya segelintir hukuman, dan
 *     (match_id, corner, tier) sudah ada.
 *
 *   matches.updated_at
 *     Justru MEMPERLAMBAT. Optimizer meninggalkan
 *     (arena_id, order_in_arena) demi memindai index baru ini, dan kuerinya
 *     melambat dari 0,455 ms ke 0,722 ms.
 *
 *   jurus_deductions (performance_id, ...)
 *     Tidak perlu: MySQL sudah membuat index untuk performance_id sebagai
 *     bagian dari foreign key-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judge_inputs', function (Blueprint $table) {
            $table->index('score_event_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->index('ratified_at');
        });
    }

    public function down(): void
    {
        Schema::table('judge_inputs', function (Blueprint $table) {
            $table->dropIndex(['score_event_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['ratified_at']);
        });
    }
};
