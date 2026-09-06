<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bagan gugur satu nomor Jurus berformat battle. Cermin Bracket untuk Tanding. */
class JurusBracket extends Model
{
    use HasFactory;

    protected $fillable = ['jurus_event_id', 'size', 'locked_at', 'locked_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'locked_at' => 'datetime'];
    }

    public function jurusEvent(): BelongsTo
    {
        return $this->belongsTo(JurusEvent::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(JurusBracketSlot::class);
    }

    public function battles(): HasMany
    {
        return $this->hasMany(JurusBattle::class);
    }

    public function terkunci(): bool
    {
        return $this->locked_at !== null;
    }
}
