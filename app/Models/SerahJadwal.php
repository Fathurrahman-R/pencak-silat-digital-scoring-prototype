<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Setengah catatan milik gelanggang PELEPAS.
 *
 * Setengahnya lagi -- `AdopsiJadwal` -- ditulis gelanggang penerima. Alasan
 * dua setengah ada di migrasi `buat_tabel_serah_jadwal`: tiap node hanya boleh
 * menulis barisnya sendiri, dan serah-terima yang ditulis satu baris berdua
 * melanggarnya tepat di titik yang aturan itu jaga.
 */
class SerahJadwal extends Model
{
    use HasUlids;

    protected $table = 'serah_jadwal';

    public const TANDING = 'tanding';

    public const JURUS = 'jurus';

    protected $fillable = [
        'arena_id',
        'ke_arena_id',
        'baris_type',
        'baris_id',
        'dilepas_pada',
        'dilepas_oleh',
        'alasan',
        'dibatalkan_pada',
        'dibatalkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'dilepas_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    /** Gelanggang pelepas -- pemilik baris ini. */
    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(Arena::class, 'ke_arena_id');
    }

    public function pelepas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilepas_oleh');
    }

    public function adopsi(): HasOne
    {
        return $this->hasOne(AdopsiJadwal::class, 'serah_id');
    }

    public function dibatalkan(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    public function sudahDiambil(): bool
    {
        return $this->adopsi()->exists();
    }

    /**
     * Serah yang masih menggantung: belum dibatalkan, belum diambil.
     *
     * Inilah keadaan yang harus terbaca di KEDUA layar. Jendela "tak bertuan"
     * tidak dihilangkan oleh rancangan ini -- ia dibuat kelihatan, karena
     * jendela yang disembunyikan adalah jendela yang baru ketahuan saat
     * pesilat sudah berdiri di matras yang salah.
     */
    public function scopeMenggantung(Builder $query): Builder
    {
        return $query->whereNull('dibatalkan_pada')->whereDoesntHave('adopsi');
    }
}
