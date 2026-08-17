<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu penampilan: satu pendaftaran tampil satu kali pada satu tahap. */
class JurusPerformance extends Model
{
    use HasFactory;

    public const STATUS_TERJADWAL = 'terjadwal';

    public const STATUS_BERLANGSUNG = 'berlangsung';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'jurus_event_id',
        'registration_id',
        'tahap',
        'arena_id',
        'order_in_arena',
        'status',
        'started_at',
        'duration_ms',
        'didiskualifikasi',
        'ratified_at',
        'ratified_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'duration_ms' => 'integer',
            'didiskualifikasi' => 'boolean',
            'ratified_at' => 'datetime',
        ];
    }

    public function jurusEvent(): BelongsTo
    {
        return $this->belongsTo(JurusEvent::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function ratifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratified_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(JurusScore::class, 'performance_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(JurusDeduction::class, 'performance_id');
    }

    public function selesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function disahkan(): bool
    {
        return $this->ratified_at !== null;
    }

    public function scopeDiGelanggang(Builder $query, Arena $arena): Builder
    {
        return $query->where('arena_id', $arena->id)->orderBy('order_in_arena');
    }
}
