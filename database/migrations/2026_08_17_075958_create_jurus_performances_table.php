<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu penampilan Jurus: satu pendaftaran (perorangan atau beregu) tampil
 * satu kali pada satu tahap satu nomor. Sejajar dengan `matches` di Tanding,
 * tapi tidak berbagi tabel dengannya -- Jurus tidak punya bagan gugur atau
 * sudut merah/biru, jadi memaksakannya ke `matches` berarti banyak kolom
 * yang selalu kosong untuk salah satu kategori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurus_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // penyisihan / semifinal / final -- Pasal 12.1.c, tiap tahap
            // punya waktu acuan sendiri untuk nomor Tunggal dan Tunggal Bebas.
            $table->string('tahap', 16)->default('final');

            $table->foreignId('arena_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('order_in_arena')->nullable();

            $table->string('status', 16)->default('terjadwal');
            $table->timestamp('started_at')->nullable();

            // Lama penampilan sesungguhnya, direkam saat timer dihentikan --
            // dipakai pemecah seri "waktu terdekat ke acuan" (Pasal 12.1.f.2)
            // dan pemeriksaan toleransi/diskualifikasi waktu.
            $table->unsignedInteger('duration_ms')->nullable();

            // Diskualifikasi ditetapkan eksplisit oleh Pengawas/Dewan Wasit
            // Juri, bukan otomatis dari selisih waktu -- naskah menyebut
            // beberapa sebab (waktu, keluar gelanggang, dst.) yang semuanya
            // butuh penilaian manusia, bukan ambang tunggal yang bisa
            // dipaksakan sistem sendirian.
            $table->boolean('didiskualifikasi')->default(false);

            $table->timestamp('ratified_at')->nullable();
            $table->foreignId('ratified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['jurus_event_id', 'registration_id', 'tahap'], 'jurus_performances_unik');
            $table->index(['arena_id', 'order_in_arena']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_performances');
    }
};
