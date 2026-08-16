<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor kategori Jurus yang dipertandingkan — Pasal 12.
 *
 * Sejajar dengan weight_classes: kalau kelas tanding membagi peserta menurut
 * berat badan, nomor jurus membaginya menurut bentuk penampilan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');
            $table->string('golongan_usia');
            $table->string('jenis_kelamin');

            /*
             * Waktu acuan penampilan dalam milidetik.
             *
             * Kosong berarti ikut waktu acuan naskah untuk nomor ini, yang
             * berbeda-beda per tahap: penyisihan, semifinal, dan final punya
             * waktu sendiri. Diisi hanya untuk nomor yang waktunya memang
             * ditetapkan panitia, bukan naskah — Ganda, Regu, dan Solo Kreatif.
             */
            $table->unsignedInteger('waktu_acuan_ms')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tournament_id', 'jenis', 'golongan_usia', 'jenis_kelamin'], 'jurus_events_unik');
            $table->index(['tournament_id', 'golongan_usia'], 'jurus_events_penelusuran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_events');
    }
};
