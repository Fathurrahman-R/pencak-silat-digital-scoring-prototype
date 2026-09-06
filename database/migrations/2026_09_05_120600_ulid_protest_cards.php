<?php

use App\Support\Sinkron\KonversiKunciUlid;
use Illuminate\Database\Migrations\Migration;

/**
 * Kunci `protest_cards` pindah ke ULID, beserta kolom yang menunjuknya.
 *
 * Tabel ketujuh, dan yang pertama punya penunjuk: `var_reviews.protest_card_id`
 * harus ikut berubah tipe dalam migrasi yang sama. Memisahkannya berarti ada
 * satu keadaan di antara dua migrasi tempat foreign key menunjuk kolom
 * bertipe berbeda -- MySQL menolaknya, dan yang gagal bukan migrasi kedua
 * melainkan yang pertama, di tengah jalan.
 *
 * Aturan ON DELETE-nya dibaca dari basis data lalu dipasang kembali persis
 * seperti semula, bukan ditebak. Menebaknya berarti mengganti cascade jadi
 * set null tanpa ada yang menyadarinya, dan yang tertinggal adalah tinjauan
 * VAR tanpa kartu protes setelah satu penghapusan.
 */
return new class extends Migration
{
    public function up(): void
    {
        KonversiKunciUlid::keUlid('protest_cards', [
            ['var_reviews', 'protest_card_id'],
        ]);
    }

    public function down(): void
    {
        KonversiKunciUlid::keInteger('protest_cards', [
            ['var_reviews', 'protest_card_id'],
        ]);
    }
};
