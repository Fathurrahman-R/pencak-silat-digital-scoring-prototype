<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif pendaftaran satu kejuaraan.
 *
 * Dua bentuk baris:
 *   nomor      biaya per nomor yang diikuti, boleh dibedakan menurut kategori
 *              dan golongan usia
 *   kontingen  biaya tetap sekali per kontingen, berapa pun atletnya
 *
 * Golongan usia yang kosong berarti tarif itu berlaku untuk semua golongan.
 * Baris yang menyebut golongan tertentu mengalahkan yang kosong, sehingga
 * panitia cukup menulis satu tarif umum lalu mengecualikan yang berbeda —
 * bukan mengisi delapan golongan kali dua kategori satu per satu.
 *
 * Nominal disimpan sebagai bilangan bulat rupiah. Rupiah tidak memakai sen
 * dalam praktik, dan tipe pecahan hanya membuka pintu bagi selisih pembulatan
 * yang harus dicocokkan dengan catatan penyelenggara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 16);
            $table->string('kategori')->nullable();
            $table->string('golongan_usia')->nullable();

            $table->unsignedInteger('amount');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'kind']);
            $table->unique(['tournament_id', 'kind', 'kategori', 'golongan_usia'], 'fee_schedules_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};
