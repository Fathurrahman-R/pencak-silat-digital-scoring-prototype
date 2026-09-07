<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serah-terima jadwal antar gelanggang, dicatat sebagai DUA setengah-catatan.
 *
 * # Persoalannya
 *
 * Memindahkan partai dari Gelanggang A ke B berarti mengubah `arena_id` --
 * kolom yang menentukan kepemilikan di sinkron. Kalau A melepas lalu B menulis
 * `arena_id` jadi miliknya, B menulis baris yang BELUM jadi miliknya pada saat
 * ia menulis, dan aturan satu penulis runtuh tepat di titik yang dibuat untuk
 * melindunginya.
 *
 * # Jalan keluarnya
 *
 * Tiap node hanya menulis barisnya sendiri:
 *
 *   A menulis `serah_jadwal`   -- apa yang dilepas, dari mana, ke mana
 *   B menulis `adopsi_jadwal`  -- menunjuk serah itu, lalu barulah B mengubah
 *                                 `arena_id`, sah karena adopsi yang tercatat
 *                                 itulah yang menyerahkan hak tulisnya
 *
 * Tidak ada satu baris pun yang pernah ditulis dua node, dan urutannya bisa
 * direkonstruksi di node mana pun tanpa membandingkan jam laptop.
 *
 * # Kenapa dua tangan
 *
 * Penerima harus menekan "Ambil". Bukan kesopanan melainkan bentuk
 * kegagalannya: tanpa persetujuan, kekeliruan baru ketahuan setelah partai
 * dipanggil dan pesilatnya berdiri di matras yang salah. Dengan persetujuan,
 * satu-satunya kegagalan yang mungkin adalah baris yang terlihat menggantung
 * di layar -- dan itu terbaca sebelum ada yang bergerak.
 *
 * # Kenapa `arena_id`, bukan `dari_arena_id`
 *
 * Kepemilikan baris LOKAL ditelusuri App\Support\Sinkron\Kepemilikan lewat
 * kolom bernama `arena_id`. Menamainya lain menuntut cabang khusus di sana --
 * satu pengecualian lagi yang harus diingat orang berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_jadwal', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Gelanggang PELEPAS: pemilik baris ini, dan yang menentukan node
            // mana yang berhak mengirimkannya.
            $table->foreignId('arena_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ke_arena_id')->constrained('arenas')->cascadeOnDelete();

            $table->string('baris_type', 16);
            $table->unsignedBigInteger('baris_id');

            $table->timestamp('dilepas_pada');
            $table->foreignId('dilepas_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alasan')->nullable();

            /*
             * Pembatalan hanya sah selama belum diambil. Sesudah diambil,
             * jalan kembalinya adalah serah-terima baru ke arah sebaliknya --
             * bukan pembatalan, karena barisnya sudah bukan milik pelepas.
             */
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Melayani daftar "ditawarkan ke saya" di panel kendali penerima.
            $table->index(['ke_arena_id', 'dibatalkan_pada']);
            $table->index(['baris_type', 'baris_id']);
        });

        Schema::create('adopsi_jadwal', function (Blueprint $table) {
            $table->ulid('id')->primary();

            /*
             * Tanpa foreign key ke `serah_jadwal`, dan itu disengaja: kedua
             * baris lahir di node yang BERBEDA dan tiba lewat penarikan yang
             * urutannya tidak dijamin. Adopsi yang sampai lebih dulu daripada
             * serahnya harus tetap tersimpan, bukan ditolak basis data.
             */
            $table->ulid('serah_id');

            // Gelanggang PENGADOPSI: pemilik baris ini.
            $table->foreignId('arena_id')->constrained()->cascadeOnDelete();

            $table->timestamp('diambil_pada');
            $table->foreignId('diambil_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique('serah_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adopsi_jadwal');
        Schema::dropIfExists('serah_jadwal');
    }
};
