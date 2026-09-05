<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partai yang sedang ditayangkan satu gelanggang.
 *
 * Sampai sekarang tidak ada satu pun tempat di basis data yang menyatakannya.
 * Ia diturunkan: partai berstatus `berlangsung` di gelanggang itu, dan kalau
 * tidak ada, partai `selesai` yang paling akhir disunting. Turunan itu bekerja
 * selama hanya ada satu perangkat yang memulai babak, tapi ia membuat setiap
 * panel ikut memutuskan partai mana yang terbuka -- dan tidak ada yang bisa
 * memindahkannya tanpa lebih dulu mengakhiri partai.
 *
 * Kolom, bukan tabel: hubungannya satu-ke-satu, dan ia dibaca di jalur
 * terpanas kedua sistem ini -- tiap tarikan overlay dan tiap resync panel.
 * Tabel dengan `unique(arena_id)` hanyalah kolom yang menuntut satu JOIN
 * tambahan pada tiap tarikan itu.
 *
 * Riwayat perpindahannya tidak hilang: ia sudah terekam di `audit_logs` dan
 * di `match_rounds.started_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arenas', function (Blueprint $table) {
            $table->foreignId('active_match_id')->nullable()->after('is_active')
                ->constrained('matches')->nullOnDelete();

            // Siapa yang menunjuknya dan kapan. Bukan untuk penegakan aturan --
            // untuk menjawab "kenapa gelanggang ini menayangkan partai itu"
            // sesudah kejadiannya lewat.
            $table->timestamp('active_match_set_at')->nullable()->after('active_match_id');
            $table->foreignId('active_match_set_by')->nullable()->after('active_match_set_at')
                ->constrained('users')->nullOnDelete();
        });

        $this->isiDariTurunanLama();
    }

    public function down(): void
    {
        Schema::table('arenas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_match_set_by');
            $table->dropColumn('active_match_set_at');
            $table->dropConstrainedForeignId('active_match_id');
        });
    }

    /**
     * Mengisi pointer dengan hasil turunan yang berlaku SEBELUM migrasi ini.
     *
     * Tanpa ini, gelanggang mana pun yang sedang menayangkan partai akan
     * kehilangan tayangannya pada detik migrasi dijalankan: pointer kosong,
     * dan overlay siaran menjawab `ada_partai: false` di tengah kejuaraan.
     *
     * Aturannya disalin persis dari StatePartaiPublik::partaiRelevan() --
     * `berlangsung` lebih dulu, kalau tidak ada ambil `selesai` yang paling
     * akhir disunting -- supaya keadaan sesudah migrasi identik dengan
     * sebelumnya, bukan sekadar mirip.
     */
    private function isiDariTurunanLama(): void
    {
        foreach (DB::table('arenas')->pluck('id') as $arenaId) {
            $matchId = DB::table('matches')
                ->where('arena_id', $arenaId)
                ->where('status', 'berlangsung')
                ->value('id')
                ?? DB::table('matches')
                    ->where('arena_id', $arenaId)
                    ->where('status', 'selesai')
                    ->orderByDesc('updated_at')
                    ->value('id');

            if ($matchId === null) {
                continue;
            }

            DB::table('arenas')->where('id', $arenaId)->update([
                'active_match_id' => $matchId,
                'active_match_set_at' => now(),
            ]);
        }
    }
};
