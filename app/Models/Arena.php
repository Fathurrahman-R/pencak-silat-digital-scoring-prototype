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
        'active_match_id',
        'active_match_set_at',
        'active_match_set_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'active_match_set_at' => 'datetime',
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

    /**
     * Partai yang sedang ditayangkan gelanggang ini.
     *
     * Satu-satunya sumber kebenarannya. Sebelum ada kolom ini, ia diturunkan
     * dari `matches.status` -- yang berarti setiap panel ikut memutuskan
     * partai mana yang terbuka, dan memindahkannya menuntut partai berjalan
     * diakhiri lebih dulu.
     *
     * Ditulis HANYA oleh App\Support\Gelanggang\PointerPartaiAktif, sama
     * seperti `current_round` yang hanya ditulis MatchTimer.
     */
    public function partaiAktif(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'active_match_id');
    }

    public function penunjukPartaiAktif(): BelongsTo
    {
        return $this->belongsTo(User::class, 'active_match_set_by');
    }

    /**
     * Pengendali yang memegang gelanggang ini.
     *
     * Terpisah dari operators(): sejak timer dan perpindahan jadwal pindah ke
     * peran Pengendali Gelanggang, keduanya bukan orang yang sama lagi.
     * Operator menjalankan papan tampilan dan perangkat siaran; pengendali
     * memimpin jalannya partai.
     */
    public function pengendali(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'arena_pengendali')->withTimestamps();
    }

    /**
     * Aparat yang bertugas di gelanggang ini sepanjang hari.
     *
     * Disalin ke `match_officials` saat pengendali menunjuk sebuah partai --
     * lihat PointerPartaiAktif. Yang disimpan di sini penugasan hariannya;
     * yang tercatat di sana siapa yang sungguh bertugas pada partai itu.
     */
    public function aparat(): HasMany
    {
        return $this->hasMany(ArenaOfficial::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
