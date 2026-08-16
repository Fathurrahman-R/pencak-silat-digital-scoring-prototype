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
             * Batas bawah kelas terendah dan batas atas kelas tertinggi boleh
             * kosong, karena naskah menuliskannya sebagai "sampai dengan" dan
             * "di atas" tanpa ujung.
             */
            $table->decimal('weight_min', 5, 1)->nullable();
            $table->decimal('weight_max', 5, 1)->nullable();

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
