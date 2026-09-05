<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua tabel yang membuat sinkron antar gelanggang bisa dilanjutkan, bukan
 * diulang dari awal tiap kali.
 *
 * # sinkron_keluar -- daftar apa saja yang berubah sejak terakhir dikirim
 *
 * Isinya PENUNJUK, bukan salinan: nama tabel dan id barisnya, tidak lebih.
 * Isi barisnya dibaca segar saat paket disusun, sehingga paket selalu membawa
 * keadaan terkini dan penerapannya idempoten -- satu paket boleh diterapkan
 * dua kali tanpa mengubah hasilnya. Menyimpan salinan isi di sini berarti
 * mengirim keadaan yang sudah lampau, dan mengurutkan beberapa perubahan atas
 * baris yang sama menjadi masalah yang harus dipecahkan.
 *
 * `id`-nya auto-increment biasa dan TIDAK pernah ikut disinkronkan. Ia murni
 * penghitung lokal, dan justru itu gunanya: peer menandai sudah sampai mana
 * ia menarik dengan menyebut angka ini.
 *
 * # Kenapa kursor berbasis penghitung, bukan stempel waktu
 *
 * Jam laptop gelanggang tidak pernah benar-benar sama. Kursor berbasis waktu
 * berarti perubahan yang terjadi pada detik yang sama dengan penarikan
 * terakhir bisa terlewat selamanya -- dan yang terlewat tidak menimbulkan
 * galat apa pun, cuma satu nilai yang tidak pernah sampai. Penghitung yang
 * naik monoton tidak punya persoalan itu.
 *
 * # sinkron_kursor -- sudah sampai mana penarikan dari tiap peer
 *
 * Disimpan per nama peer, bukan per url: alamat IP laptop berubah tiap kali
 * DHCP berbaik hati, sementara namanya ("gelanggang-b") tidak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinkron_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('tabel', 64);

            /*
             * String, bukan integer, dan sengaja selebar ini: id baris hasil
             * pertandingan akan berpindah ke ULID supaya dua gelanggang tidak
             * menerbitkan id yang sama. Kolom ini sudah siap menerimanya
             * sekarang, jadi perpindahan itu tidak menyeret migrasi kedua.
             */
            $table->string('baris_id', 64);

            $table->string('aksi', 8); // simpan | hapus
            $table->timestamp('dicatat_pada', 3);

            /*
             * Penarikan membaca berurutan dari kursor: `where id > ? order by
             * id`. Primary key sudah melayaninya. Index kedua ini untuk arah
             * sebaliknya -- menjawab "baris ini sudah tercatat belum" saat
             * memadatkan antrean.
             */
            $table->index(['tabel', 'baris_id']);
        });

        Schema::create('sinkron_kursor', function (Blueprint $table) {
            $table->string('peer', 64)->primary();
            $table->unsignedBigInteger('kursor_terakhir')->default(0);
            $table->timestamp('ditarik_pada')->nullable();
            $table->unsignedInteger('baris_diterapkan')->default(0);
            $table->string('galat_terakhir')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinkron_kursor');
        Schema::dropIfExists('sinkron_keluar');
    }
};
