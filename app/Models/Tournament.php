<?php

namespace App\Models;

use App\Enums\StatusTurnamen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tournament extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'organizer',
        'venue',
        'starts_on',
        'ends_on',
        'registration_opens_at',
        'registration_closes_at',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusTurnamen::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    public function ruleSetting(): HasOne
    {
        return $this->hasOne(TournamentRuleSetting::class);
    }

    public function arenas(): HasMany
    {
        return $this->hasMany(Arena::class)->orderBy('sort_order');
    }

    public function weightClasses(): HasMany
    {
        return $this->hasMany(WeightClass::class)->orderBy('sort_order');
    }

    public function jurusEvents(): HasMany
    {
        return $this->hasMany(JurusEvent::class)->orderBy('sort_order');
    }

    public function contingents(): HasMany
    {
        return $this->hasMany(Contingent::class);
    }

    public function feeSchedules(): HasMany
    {
        return $this->hasMany(FeeSchedule::class);
    }

    /**
     * Setelan peraturan yang berlaku, dijamin ada.
     *
     * Kejuaraan tanpa setelan tidak bisa menjalankan satu partai pun, jadi
     * ketiadaannya diperlakukan sebagai keadaan yang harus diperbaiki saat itu
     * juga — bukan dibiarkan mengalir sampai gelanggang dan gagal di sana.
     */
    public function peraturan(): TournamentRuleSetting
    {
        return $this->ruleSetting()->first()
            ?? throw new \RuntimeException("Turnamen [{$this->id}] belum punya setelan peraturan.");
    }

    /** Pendaftaran hanya terbuka di dalam jendela yang ditetapkan panitia. */
    public function pendaftaranTerbuka(): bool
    {
        $sekarang = now();

        return $this->status === StatusTurnamen::Draf
            && (! $this->registration_opens_at || $this->registration_opens_at->lte($sekarang))
            && (! $this->registration_closes_at || $this->registration_closes_at->gte($sekarang));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('organizer', 'like', "%{$term}%")
                ->orWhere('venue', 'like', "%{$term}%");
        });
    }
}
