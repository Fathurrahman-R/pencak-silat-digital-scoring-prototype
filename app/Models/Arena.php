<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Arena extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SilatMatch::class);
    }

    public function jurusPerformances(): HasMany
    {
        return $this->hasMany(JurusPerformance::class);
    }

    /**
     * Operator yang memegang gelanggang ini.
     *
     * Ditugaskan per gelanggang, bukan per partai: satu operator duduk di
     * satu gelanggang sepanjang hari, dan penugasannya ikut berlaku untuk
     * partai yang baru dijadwalkan ke sini kemudian.
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'arena_operators')->withTimestamps();
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
