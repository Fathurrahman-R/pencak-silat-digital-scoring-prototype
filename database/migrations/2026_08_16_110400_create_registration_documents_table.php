<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas persyaratan, melekat pada atlet — bukan pada pendaftaran.
 *
 * Satu atlet lazim mendaftar ke lebih dari satu nomor, dan akta kelahirannya
 * tetap satu. Menempelkannya ke pendaftaran berarti official mengunggah berkas
 * yang sama berkali-kali dan panitia memeriksanya berkali-kali pula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size_bytes');
            $table->string('mime', 100);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['athlete_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_documents');
    }
};
