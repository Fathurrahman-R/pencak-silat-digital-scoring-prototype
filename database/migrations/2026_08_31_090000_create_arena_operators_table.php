<?php

use App\Models\Arena;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operator gelanggang.
 *
 * Berbeda dari wasit dan juri yang ditugaskan per partai, satu operator
 * duduk di satu gelanggang sepanjang hari. Jadi penugasannya diberikan
 * sekali per gelanggang dan berlaku untuk seluruh partai yang dimainkan
 * di sana -- termasuk partai yang baru dijadwalkan setelahnya.
 *
 * Tanpa tabel ini, izin `partai.update` dan `partai.manage` berlaku untuk
 * seluruh kejuaraan: operator gelanggang A bisa mereset timer atau
 * mengakhiri partai yang sedang berjalan di gelanggang B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arena_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['arena_id', 'user_id']);
        });

        /*
         * Backfill: seluruh operator yang sudah ada ditugaskan ke seluruh
         * gelanggang yang sudah ada, supaya kejuaraan yang sedang berjalan
         * tidak mendadak kehilangan operatornya saat migrasi dijalankan.
         *
         * Penyempitannya diserahkan ke panitia lewat halaman Gelanggang.
         * Gelanggang yang dibuat SETELAH migrasi ini tidak ikut di-backfill,
         * jadi gelanggang baru terjaga sejak awal.
         */
        $operator = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'operator-it')
            ->pluck('users.id');

        if ($operator->isEmpty()) {
            return;
        }

        $sekarang = now();

        foreach (Arena::query()->pluck('id') as $arenaId) {
            DB::table('arena_operators')->insert(
                $operator->map(fn ($userId) => [
                    'arena_id' => $arenaId,
                    'user_id' => $userId,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ])->all(),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_operators');
    }
};
