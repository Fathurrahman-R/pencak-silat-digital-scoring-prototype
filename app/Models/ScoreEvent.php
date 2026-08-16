<?php

namespace App\Models;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu nilai yang sah karena mencapai ambang konsensus juri.
 *
 * Koreksi dewan juri tidak menyunting baris ini -- ia dibatalkan lewat
 * `voided_at` beserta alasannya, supaya jejak penilaian tetap utuh.
 */
class ScoreEvent extends Model
{
    use HasFactory;

    /** Lihat catatan yang sama di App\Models\JudgeInput. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'match_id',
        'round',
        'corner',
        'point_type',
        'value',
        'server_ts',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'corner' => Sudut::class,
            'point_type' => JenisSerangan::class,
            'value' => 'integer',
            'server_ts' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
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
