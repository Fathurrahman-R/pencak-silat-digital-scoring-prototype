<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Protes Manajer berjenjang -- Pasal 15 ayat 4. */
class ManagerProtest extends Model
{
    use HasFactory;

    public const TINGKAT_PERTAMA = 'pertama';

    public const BANDING = 'banding';

    public const DITERIMA = 'diterima';

    public const DITOLAK = 'ditolak';

    protected $fillable = [
        'match_id',
        'level',
        'parent_id',
        'diajukan_at',
        'tenggat_formulir_at',
        'tenggat_keputusan_at',
        'formulir_dikembalikan_at',
        'diputuskan_at',
        'keputusan',
        'diputuskan_oleh',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'diajukan_at' => 'datetime',
            'tenggat_formulir_at' => 'datetime',
            'tenggat_keputusan_at' => 'datetime',
            'formulir_dikembalikan_at' => 'datetime',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function banding(): HasOne
    {
        return $this->hasOne(self::class, 'parent_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function sudahDiputuskan(): bool
    {
        return $this->diputuskan_at !== null;
    }

    public function final(): bool
    {
        return $this->level === self::BANDING && $this->sudahDiputuskan();
    }
}
