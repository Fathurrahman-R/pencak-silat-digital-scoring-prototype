<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contingent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'name',
        'region',
        'contact_name',
        'contact_phone',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** Official yang mengelola kontingen ini. */
    public function official(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(RegistrationDocument::class, Athlete::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('region', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%");
        });
    }

    /** Kontingen yang dikelola satu official. */
    public function scopeDikelolaOleh(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
