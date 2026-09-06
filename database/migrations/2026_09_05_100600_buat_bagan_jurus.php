<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagan gugur untuk nomor Jurus berformat battle.
 *
 * # Kenapa tabel sendiri, bukan menumpang `brackets` dan `matches`
 *
 * `brackets.weight_class_id` tidak boleh kosong, dan rantai
 * `bracket->weightClass->tournament` dipakai 115 kali di 38 berkas -- termasuk
 * lima belas kali di PartaiScoringController dan belasan di Blade. Membuat
 * kolom itu nullable berarti setiap titik tersebut harus menahan `null`, dan
 * yang terlewat baru ketahuan sebagai 500 di tengah kejuaraan.
 *
 * Tabel terpisah menukar risiko regresi Tanding dengan sedikit duplikasi di
 * sisi Jurus. Duplikasi itu sendiri ditekan seminimal mungkin: UrutanUnggulan,
 * PohonBagan, dan PromosiPemenang semuanya dipakai ulang apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurus_event_id')->unique()->constrained()->cascadeOnDelete();

            // Selalu pangkat dua. Tempat yang tidak terisi peserta jadi bye.
            $table->unsignedSmallInteger('size');

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jurus_bracket_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurus_bracket_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['jurus_bracket_id', 'position']);
            // Satu peserta tidak boleh menempati dua tempat di bagan yang sama.
            $table->unique(['jurus_bracket_id', 'registration_id'], 'jurus_slots_peserta_unik');
        });

        Schema::create('jurus_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurus_bracket_id')->constrained()->cascadeOnDelete();

            // Koordinat bagan, sama artinya dengan `matches`: ronde gugur dan
            // nomor urut partai di dalam ronde itu.
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');

            /*
             * Naskah Pasal 12.1.d.7: penampilan pertama dilakukan Pesilat sudut
             * BIRU, dilanjutkan sudut merah. Sudutnya karena itu bukan sekadar
             * label -- ia menentukan urutan tampil.
             */
            $table->foreignId('red_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('blue_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('winner_registration_id')->nullable()->constrained('registrations')->nullOnDelete();

            $table->string('win_reason', 32)->nullable();
            $table->string('status', 24)->default('terjadwal');

            $table->foreignId('arena_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('order_in_arena')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();

            $table->unique(['jurus_bracket_id', 'round', 'position'], 'jurus_battles_koordinat_unik');
            $table->index(['arena_id', 'order_in_arena']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_battles');
        Schema::dropIfExists('jurus_bracket_slots');
        Schema::dropIfExists('jurus_brackets');
    }
};
