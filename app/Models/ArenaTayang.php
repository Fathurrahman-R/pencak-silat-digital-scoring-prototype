<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apa yang sedang ditayangkan satu gelanggang.
 *
 * Satu baris per gelanggang, dan barisnya milik node yang memegang gelanggang
 * itu -- bukan node global. Itulah seluruh alasan tabel ini ada; lihat
 * migrasi `buat_tabel_arena_tayang` untuk cacat yang dilahirkannya saat
 * pointer ini masih berupa kolom di `arenas`.
 *
 * ULID, seperti seluruh tabel yang lahir di gelanggang: penghitung
 * auto-increment tiap basis data mulai dari satu, jadi dua gelanggang akan
 * menerbitkan baris bernomor sama dan penggabungannya menghapus catatan
 * sungguhan.
 */
class ArenaTayang extends Model
{
    use HasUlids;

    /** Satu-satunya nama tabelnya; jamak-nya tidak beraturan di bahasa ini. */
    protected $table = 'arena_tayang';

    public const TANDING = 'tanding';

    public const JURUS = 'jurus';

    protected $fillable = [
        'arena_id',
        'tayang_type',
        'tayang_id',
        'disetel_pada',
        'disetel_oleh',
    ];

    protected function casts(): array
    {
        return [
            'disetel_pada' => 'datetime',
        ];
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function penyetel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetel_oleh');
    }

    /**
     * Partai Tanding yang ditunjuk, kalau memang partai yang ditayangkan.
     *
     * Relasi terpisah per jenis, bukan satu morphTo: kedua jenis dibaca di
     * jalur yang berbeda dan hampir tidak pernah dibutuhkan bersamaan, jadi
     * morphTo cuma menambah satu lapis tebakan tanpa menghemat query.
     */
    public function partai(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'tayang_id')
            ->where('arena_tayang.tayang_type', self::TANDING);
    }

    public function menayangkanPartai(): bool
    {
        return $this->tayang_type === self::TANDING && $this->tayang_id !== null;
    }

    public function menayangkanJurus(): bool
    {
        return $this->tayang_type === self::JURUS && $this->tayang_id !== null;
    }
}
