<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kejuaraan. Akar dari hampir seluruh data domain.
 *
 * Nama tabel memakai bahasa Inggris mengikuti kebiasaan boilerplate, tetapi
 * kolom yang menamai hal khas pencak silat tetap memakai istilah aslinya —
 * "golongan usia" dan "jurus" tidak punya padanan Inggris yang jujur, dan
 * menerjemahkannya hanya membuat panitia harus menebak saat mencocokkan
 * dengan naskah peraturan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('organizer')->nullable();
            $table->string('venue')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            // Jendela pendaftaran kontingen. Terpisah dari tanggal acara karena
            // pendaftaran selalu ditutup jauh sebelum hari pertama bertanding.
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();

            $table->string('status')->default('draf');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
