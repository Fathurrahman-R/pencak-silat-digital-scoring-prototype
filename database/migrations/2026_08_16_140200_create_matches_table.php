<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partai: satu pertemuan dua pesilat di satu tempat pada bagan.
 *
 * Letaknya ditentukan babak dan nomor urut. Pemenang partai (babak r, nomor p)
 * naik ke partai (babak r+1, nomor ceil(p/2)) — menempati sudut merah bila p
 * ganjil dan sudut biru bila genap. Aturan itu tidak disimpan sebagai kolom
 * karena bisa dihitung, dan kolom yang bisa dihitung adalah kolom yang bisa
 * bertentangan dengan kenyataan.
 *
 * Nama tabel `matches` dipertahankan meski `match` adalah kata kunci PHP;
 * yang bertabrakan hanya nama kelas, dan modelnya bernama SilatMatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');

            /*
             * Merah dan biru adalah identitas sudut yang ditetapkan peraturan,
             * bukan sekadar urutan. Keduanya boleh kosong selama pemenang
             * partai sebelumnya belum ada.
             */
            $table->foreignId('red_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('blue_registration_id')->nullable()->constrained('registrations')->nullOnDelete();

            $table->foreignId('winner_registration_id')->nullable()->constrained('registrations')->nullOnDelete();

            // Sebab kemenangan: angka, teknik, mutlak, WMP, undur diri,
            // diskualifikasi, atau bye. Diisi saat partai selesai.
            $table->string('win_reason', 32)->nullable();

            $table->string('status', 24)->default('terjadwal');

            // Penempatan ke gelanggang dan urutan tayangnya. Kosong berarti
            // belum dijadwalkan.
            $table->foreignId('arena_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('order_in_arena')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();

            $table->unique(['bracket_id', 'round', 'position']);
            $table->index(['arena_id', 'order_in_arena']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
