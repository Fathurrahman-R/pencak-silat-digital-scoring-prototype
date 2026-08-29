<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifikasi juri -- Pasal 13.
 *
 * Wasit atau Ketua Pertandingan yang ragu sudut mana yang menjatuhkan atau
 * melanggar menghentikan pertandingan dan menanyakannya ke tiga juri. Jawaban
 * mereka dihitung dengan ambang yang sama dengan penilaian biasa.
 *
 * Satu baris di sini adalah satu pertanyaan. Jawabannya di tabel terpisah,
 * satu baris per juri, karena jawaban tiap juri harus bisa ditampilkan sendiri
 * di berita acara dan diprotes pelatih lewat Kartu Protes (Pasal 15).
 *
 * `score_event_id` dan `penalty_id` diisi SETELAH hasilnya diterapkan, bukan
 * saat pertanyaan dibuat -- keduanya adalah akibat, bukan sebab. Keduanya
 * juga boleh tetap kosong: verifikasi yang hasilnya "tidak ada" tidak
 * menerbitkan nilai maupun hukuman, dan tetap harus tercatat pernah terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');

            $table->string('jenis', 16);            // jatuhan | pelanggaran

            /*
             * Tingkat pelanggaran ditetapkan saat BERTANYA, bukan setelah
             * jawabannya masuk. Wasit yang meminta verifikasi pelanggaran
             * sudah tahu pelanggaran apa yang dilihatnya -- yang ia ragukan
             * hanya sudut mana yang melakukannya. Menetapkannya di depan
             * membuat akibat verifikasi bisa dinyatakan penuh sebelum
             * diterapkan ("Teguran untuk sudut merah, -1"), bukan disodorkan
             * sebagai keputusan kedua setelah juri terlanjur menjawab.
             *
             * Kosong untuk verifikasi jatuhan: nilainya sudah tetap 3.
             */
            $table->string('tingkat_pelanggaran', 16)->nullable(); // ringan | sedang | berat

            /*
             * Kejadian yang ditanyakan, kalau wasit menunjuk satu. Boleh
             * kosong: wasit yang menghentikan pertandingan tepat setelah
             * kejadian sering tidak punya nilai atau hukuman untuk ditunjuk
             * -- justru itu yang sedang ditanyakan.
             */
            $table->foreignId('score_event_id')->nullable()->constrained('score_events')->nullOnDelete();
            $table->foreignId('penalty_id')->nullable()->constrained('penalties')->nullOnDelete();

            $table->foreignId('diminta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diminta_at', 3);

            $table->string('status', 16);           // berjalan | selesai | dibatalkan

            /*
             * Hasil polling. Diisi begitu ambang tercapai, dan tidak berubah
             * lagi setelah itu -- jawaban juri yang datang terlambat tercatat
             * di tabel jawaban, tapi tidak menggeser hasil yang sudah bulat.
             */
            $table->string('hasil', 16)->nullable();  // red | blue | tidak_ada
            $table->timestamp('hasil_at', 3)->nullable();

            $table->timestamp('diterapkan_at', 3)->nullable();
            $table->foreignId('diterapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan')->nullable();

            $table->timestamps();

            /*
             * Panel menanyakan "adakah verifikasi berjalan di partai ini"
             * pada tiap penyegaran state, dan itu jalur terpanas tabel ini.
             */
            $table->index(['match_id', 'status']);
        });

        Schema::create('judge_verification_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_verification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Nomor juri disalin, tidak cuma dirujuk lewat match_officials.
             * Penugasan aparat bisa berubah setelah partai selesai, dan berita
             * acara harus tetap menyebut "Juri 2" seperti saat kejadiannya.
             */
            $table->unsignedTinyInteger('judge_number')->nullable();

            $table->string('jawaban', 16);          // red | blue | tidak_ada

            // Stempel waktu resmi dari server. Jam perangkat juri tidak pernah
            // dipercaya -- aturan yang sama dengan judge_inputs.
            $table->timestamp('server_ts', 3);

            $table->timestamps();

            /*
             * Satu juri satu jawaban. Tanpa ini, juri yang menekan dua kali
             * karena panelnya lambat akan terhitung dua suara dan bisa
             * memenangkan pilihannya sendirian.
             */
            $table->unique(['judge_verification_id', 'judge_user_id'], 'jawaban_verifikasi_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_verification_answers');
        Schema::dropIfExists('judge_verifications');
    }
};
