<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas tanding — Pasal 10.
 *
 * Kelas terikat pada satu kejuaraan, bukan berlaku global. Panitia lazim hanya
 * mempertandingkan sebagian kelas, dan menyimpannya global berarti setiap
 * kejuaraan harus mematikan kelas yang tidak dipakai satu per satu.
 *
 * Dua golongan tidak punya baris di sini sama sekali: Pra Usia Dini tidak
 * mempertandingkan kategori Tanding, dan Usia Dini 1 bertanding tanpa
 * pembagian kelas berat. Lihat App\Enums\GolonganUsia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('golongan_usia');
            $table->string('jenis_kelamin');

            // Huruf kelas sebagaimana disebut naskah: Kelas A, B, C, dan
            // seterusnya. Bukan nomor urut — huruf inilah yang dipanggil
            // announcer dan dicetak di bagan.
            $table->string('code', 8);
            $table->string('name');

            /*
             * Batas berat dalam kilogram, satu angka di belakang koma
             * mengikuti ketelitian timbangan yang dipakai.
             *
             * Kosong berarti tanpa ujung: kelas terendah tidak punya batas
             * bawah, kelas tertinggi tidak punya batas atas.
             */
            $table->decimal('weight_min', 5, 1)->nullable();
            $table->decimal('weight_max', 5, 1)->nullable();

            /*
             * Naskah memakai tiga rumusan yang inklusivitasnya berbeda-beda,
             * dan perbedaan itu menentukan kelas mana yang menerima atlet yang
             * beratnya jatuh persis di angka batas:
             *
             *   "Diatas 43 kg sampai 47 kg"  batas bawah eksklusif, atas inklusif
             *   "39 kg sampai 43 kg"         kedua batas inklusif
             *   "Dibawah 39 kg"              batas atas eksklusif
             *
             * Tanpa kedua penanda ini, kelas Remaja "Dibawah 39 kg" dan kelas A
             * "39 kg sampai 43 kg" akan sama-sama mengaku memuat atlet 39,0 kg,
             * sementara kelas terendah Usia Dini 2 "26 kg sampai 28 kg" justru
             * menolak atlet 26,0 kg yang seharusnya diterimanya.
             *
             * Bawaannya mengikuti rumusan yang paling sering dipakai naskah.
             */
            $table->boolean('weight_min_exclusive')->default(true);
            $table->boolean('weight_max_inclusive')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tournament_id', 'golongan_usia', 'jenis_kelamin', 'code'], 'weight_classes_unik');
            $table->index(['tournament_id', 'golongan_usia', 'jenis_kelamin'], 'weight_classes_penelusuran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_classes');
    }
};
