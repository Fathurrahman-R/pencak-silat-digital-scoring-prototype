<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setengah catatan milik gelanggang PENERIMA.
 *
 * Ia yang menyerahkan hak tulis: sesudah baris ini ada, node penerima boleh
 * mengubah `arena_id` baris yang diserahkan. Sebelum ada, baris itu masih
 * milik pelepas dan penerima tidak boleh menyentuhnya sama sekali.
 */
class AdopsiJadwal extends Model
{
    use HasUlids;

    protected $table = 'adopsi_jadwal';

    protected $fillable = [
        'serah_id',
        'arena_id',
        'diambil_pada',
        'diambil_oleh',
    ];

    protected function casts(): array
    {
        return [
            'diambil_pada' => 'datetime',
        ];
    }

    public function serah(): BelongsTo
    {
        return $this->belongsTo(SerahJadwal::class, 'serah_id');
    }

    /** Gelanggang pengadopsi -- pemilik baris ini. */
    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function pengambil(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diambil_oleh');
    }
}
