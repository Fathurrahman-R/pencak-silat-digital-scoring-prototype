<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua tabel arsip bukti, plus satu penanda di partai.
 *
 * # arsip_keluar -- sisi gelanggang
 *
 * Antrean partai yang buktinya perlu dikirim ke node global. Ada karena node
 * global bisa mati, dan matinya tidak boleh menghentikan pertandingan:
 * pengesahan tetap berhasil, arsipnya menunggu di sini, disapu ulang nanti.
 *
 * `match_id` jadi kunci utamanya, bukan id sendiri. Satu partai punya tepat
 * satu baris antrean, dan kunci yang menegakkannya lebih baik daripada
 * penjagaan di kode yang harus diingat tiap kali ada jalur baru yang
 * mengantrekan.
 *
 * # arsip_partai -- sisi node global
 *
 * Catatan berkas arsip yang benar-benar tersimpan. BUKAN isinya: isinya ada
 * di storage sebagai berkas beku, dan tabel ini cuma menyebut di mana, seberapa
 * besar, dan sidik jarinya apa.
 *
 * Pasangan (match_id, versi) unik, dan versinya naik: partai yang sama bisa
 * dikirim ulang setelah babak susulan mengubah hasilnya. Yang lama TIDAK
 * ditimpa -- justru perubahan itulah yang paling mungkin dipersoalkan, dan
 * menjawabnya butuh kedua keadaan.
 *
 * # matches.judge_inputs_dipangkas_pada
 *
 * Menandai bahwa rincian penekanan tombol partai ini sudah pindah ke node
 * arsip. Panel membacanya untuk mengatakannya kepada yang membuka riwayat,
 * alih-alih menampilkan daftar kosong seolah tidak ada satu pun juri yang
 * menekan tombol pada partai itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arsip_keluar', function (Blueprint $table) {
            $table->char('match_id', 26)->primary();

            // menunggu | diterima | gagal
            $table->string('status', 16)->default('menunggu');

            /*
             * Angka ini yang membedakan "gagal sekali karena kabel tersenggol"
             * dari "gagal dua puluh kali karena alamatnya memang salah". Yang
             * pertama sembuh sendiri pada sapuan berikutnya; yang kedua butuh
             * orang membuka .env.
             */
            $table->unsignedInteger('percobaan')->default(0);

            $table->char('checksum', 64)->nullable();
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedBigInteger('ukuran_bita')->default(0);
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamp('diterima_pada')->nullable();
            $table->string('galat_terakhir')->nullable();
            $table->timestamps();

            // Sapuan mencari yang belum diterima, paling lama menunggu duluan.
            $table->index(['status', 'updated_at']);
        });

        Schema::create('arsip_partai', function (Blueprint $table) {
            $table->id();
            $table->char('match_id', 26);
            $table->unsignedInteger('versi');
            $table->string('node_asal', 64);
            $table->char('checksum', 64);
            $table->unsignedInteger('jumlah_baris');
            $table->unsignedBigInteger('ukuran_bita');
            $table->string('jalur_berkas');
            $table->timestamp('dibekukan_pada')->nullable();
            $table->timestamp('diterima_pada');
            $table->timestamps();

            /*
             * Tanpa foreign key ke matches, dan itu disengaja. Node global
             * menerima arsip dari empat gelanggang; partainya mungkin belum
             * ikut tersinkron saat paketnya tiba, dan bukti tidak boleh
             * ditolak karena urutan kedatangan.
             */
            $table->unique(['match_id', 'versi']);
            $table->index('diterima_pada');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('judge_inputs_dipangkas_pada')->nullable()->after('snapshot_pada');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('judge_inputs_dipangkas_pada');
        });

        Schema::dropIfExists('arsip_partai');
        Schema::dropIfExists('arsip_keluar');
    }
};
