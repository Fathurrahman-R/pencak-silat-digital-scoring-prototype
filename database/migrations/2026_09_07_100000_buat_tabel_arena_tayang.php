<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Apa yang sedang ditayangkan gelanggang, pindah keluar dari `arenas`.
 *
 * # Kenapa dipindah
 *
 * `arenas` bergolongan GLOBAL di App\Support\Sinkron\PetaSinkron: hanya node
 * global yang boleh menulisnya, dan node gelanggang menerimanya read-only.
 * Tapi `active_match_id` justru ditulis node gelanggang -- pengendali yang
 * memilih partai. Satu tabel, dua penulis, tepat pada aturan yang dibuat
 * untuk mencegah itu.
 *
 * Yang membuatnya berbahaya bukan teorinya melainkan PenerapPaket: tiap baris
 * diterapkan sebagai `upsert(..., array_keys($data))` -- seluruh kolom ditimpa,
 * tanpa penyaringan. Begitu node global menyunting satu gelanggang apa pun
 * (ganti nama, nonaktifkan), penarikan berikutnya membawa `active_match_id`
 * versi global, yang selalu kosong, dan gelanggang yang sedang bertanding
 * kehilangan partai aktifnya. Tanpa galat, tanpa baris log: yang terlihat cuma
 * seluruh panel serentak kembali ke "menunggu pengendali memilih partai".
 *
 * Menambahkan daftar kolom-yang-dikecualikan ke sinkron akan menutupnya juga,
 * tapi dengan aturan tak tertulis -- dan aturan tak tertulis semacam itulah
 * yang melahirkan cacat ini. Pointer pindah ke tabel yang tiap barisnya milik
 * tepat satu gelanggang, bentuk yang sama dengan seluruh tabel LOKAL lain.
 *
 * # Kenapa polimorfik sejak sekarang
 *
 * Kategori Jurus akan mengikuti pola yang sama: pengendali memilih penampilan,
 * panel mengikutinya. Kolom `tayang_type`/`tayang_id` menampung keduanya, jadi
 * langkah itu tidak butuh migrasi kedua yang menyentuh tabel yang sama.
 *
 * Satu gelanggang menayangkan SATU hal pada satu waktu -- partai Tanding atau
 * penampilan Jurus, tidak pernah keduanya. Satu matras memang cuma bisa
 * dipakai satu hal.
 *
 * # Kenapa tanpa foreign key pada `tayang_id`
 *
 * Ia menunjuk dua tabel. Keutuhannya dijaga aplikasi, pola yang sama dengan
 * `auditable_id` di `audit_logs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_tayang', function (Blueprint $table) {
            $table->ulid('id')->primary();

            /*
             * UNIQUE: satu gelanggang satu baris. Barisnya tidak dihapus saat
             * gelanggang dikosongkan -- kolom tayang yang dikosongkan, supaya
             * `disetel_oleh` tetap menyimpan siapa yang mengosongkannya.
             * "Kosongkan gelanggang" adalah tindakan sadar yang perlu jejak.
             */
            $table->foreignId('arena_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('tayang_type', 16)->nullable();
            $table->unsignedBigInteger('tayang_id')->nullable();

            $table->timestamp('disetel_pada')->nullable();
            $table->foreignId('disetel_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Dibaca saat menjawab "gelanggang mana yang sedang menayangkan
            // partai ini" -- dipakai penjadwal dan pemangkas riwayat juri.
            $table->index(['tayang_type', 'tayang_id']);
        });

        $this->pindahkanDariArenas();

        Schema::table('arenas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_match_set_by');
            $table->dropColumn('active_match_set_at');
            $table->dropConstrainedForeignId('active_match_id');
        });
    }

    public function down(): void
    {
        Schema::table('arenas', function (Blueprint $table) {
            $table->foreignId('active_match_id')->nullable()->after('is_active')
                ->constrained('matches')->nullOnDelete();
            $table->timestamp('active_match_set_at')->nullable()->after('active_match_id');
            $table->foreignId('active_match_set_by')->nullable()->after('active_match_set_at')
                ->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('arena_tayang')->where('tayang_type', 'tanding')->get() as $baris) {
            DB::table('arenas')->where('id', $baris->arena_id)->update([
                'active_match_id' => $baris->tayang_id,
                'active_match_set_at' => $baris->disetel_pada,
                'active_match_set_by' => $baris->disetel_oleh,
            ]);
        }

        Schema::dropIfExists('arena_tayang');
    }

    /**
     * Satu baris per gelanggang yang sedang menayangkan sesuatu.
     *
     * Gelanggang yang pointernya kosong tidak diberi baris di sini: barisnya
     * lahir saat pengendali pertama kali memilih partai. Memberi semua
     * gelanggang baris kosong berarti menulis catatan yang mengaku ada
     * keputusan padahal belum ada yang memutuskan apa pun.
     */
    private function pindahkanDariArenas(): void
    {
        if (! Schema::hasColumn('arenas', 'active_match_id')) {
            return;
        }

        $baris = DB::table('arenas')
            ->whereNotNull('active_match_id')
            ->get(['id', 'active_match_id', 'active_match_set_at', 'active_match_set_by']);

        if ($baris->isEmpty()) {
            return;
        }

        DB::table('arena_tayang')->insert($baris->map(fn ($satu) => [
            'id' => (string) Str::ulid(),
            'arena_id' => $satu->id,
            'tayang_type' => 'tanding',
            'tayang_id' => $satu->active_match_id,
            'disetel_pada' => $satu->active_match_set_at,
            'disetel_oleh' => $satu->active_match_set_by,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }
};
