<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengurangan nilai -- 0.01 oleh juri, 0.50 oleh Pengawas/Dewan Wasit Juri. */
class JurusDeduction extends Model
{
    use HasFactory;

    public const TIER_JURI = 'juri';

    public const TIER_PENGAWAS = 'pengawas';

    protected $fillable = [
        'performance_id',
        'tier',
        'alasan',
        'jumlah',
        'created_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(JurusPerformance::class, 'performance_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function dibatalkan(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
