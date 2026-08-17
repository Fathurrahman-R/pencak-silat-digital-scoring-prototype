<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use App\Enums\JenisJurus;
use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JurusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'jenis',
        'golongan_usia',
        'jenis_kelamin',
        'waktu_acuan_ms',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisJurus::class,
            'golongan_usia' => GolonganUsia::class,
            'jenis_kelamin' => JenisKelamin::class,
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(JurusPerformance::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function nama(): string
    {
        return "{$this->jenis->label()} {$this->jenis_kelamin->label()} {$this->golongan_usia->label()}";
    }

    /**
     * Waktu acuan penampilan untuk satu tahap, dalam milidetik.
     *
     * Nomor yang waktunya diatur naskah membacanya dari config/scoring.php per
     * tahap; sisanya memakai angka yang ditetapkan panitia di baris ini.
     */
    public function waktuAcuanMs(string $tahap = 'final'): ?int
    {
        if ($this->waktu_acuan_ms !== null) {
            return $this->waktu_acuan_ms;
        }

        $kunci = $this->jenis->kunciWaktuAcuan();

        return $kunci === null
            ? null
            : config("scoring.jurus.waktu_acuan_ms.{$kunci}.{$tahap}");
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
