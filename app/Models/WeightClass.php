<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'golongan_usia',
        'jenis_kelamin',
        'code',
        'name',
        'weight_min',
        'weight_max',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'golongan_usia' => GolonganUsia::class,
            'jenis_kelamin' => JenisKelamin::class,
            'weight_min' => 'decimal:1',
            'weight_max' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Apakah berat badan ini masuk kelas.
     *
     * Batas bawah eksklusif, batas atas inklusif — mengikuti cara naskah
     * menuliskannya: "di atas 45 kg sampai dengan 50 kg". Ditulis begini
     * supaya kelas yang bersebelahan tidak pernah tumpang tindih di angka
     * batasnya, yang berarti satu berat badan selalu jatuh ke tepat satu kelas.
     */
    public function memuatBerat(float $kg): bool
    {
        if ($this->weight_min !== null && $kg <= (float) $this->weight_min) {
            return false;
        }

        if ($this->weight_max !== null && $kg > (float) $this->weight_max) {
            return false;
        }

        return true;
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUntuk(Builder $query, GolonganUsia $golongan, JenisKelamin $jenisKelamin): Builder
    {
        return $query
            ->where('golongan_usia', $golongan)
            ->where('jenis_kelamin', $jenisKelamin);
    }
}
