<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran satu peserta ke satu kelas tanding atau satu nomor jurus.
 *
 * Satu baris berarti satu peserta pertandingan, bukan satu orang: nomor Ganda
 * diisi dua atlet dan nomor Regu diisi tiga, tetapi ketiganya tetap satu
 * pendaftaran. Bentuk ini yang membuat penagihan benar tanpa aturan
 * tambahan — nomor beregu memang ditagih per tim, bukan per orang.
 *
 * Karena itu atletnya tidak ditaruh sebagai kolom di sini melainkan lewat
 * tabel penghubung registration_athlete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingent_id')->constrained()->cascadeOnDelete();

            /*
             * Tepat satu dari keduanya terisi. Tidak digabung menjadi satu
             * kolom polimorfik karena keduanya punya aturan kelayakan yang
             * berbeda sama sekali, dan kunci asing yang sungguhan membuat
             * kelas yang dihapus tidak meninggalkan pendaftaran menggantung.
             */
            $table->foreignId('weight_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('jurus_event_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('status')->default('draf');

            // Alasan wajib diisi saat menolak, supaya kontingen tahu apa yang
            // harus diperbaiki dan panitia bisa mempertanggungjawabkannya.
            $table->text('rejection_reason')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['contingent_id', 'status']);
            $table->index('weight_class_id');
            $table->index('jurus_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
