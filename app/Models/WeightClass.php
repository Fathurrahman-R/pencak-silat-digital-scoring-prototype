<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Concerns\UrutGolonganUsia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WeightClass extends Model
{
    use HasFactory;
    use UrutGolonganUsia;

    protected $fillable = [
        'tournament_id',
        'golongan_usia',
        'jenis_kelamin',
        'code',
        'name',
        'weight_min',
        'weight_max',
        'weight_min_exclusive',
        'weight_max_inclusive',
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
            'weight_min_exclusive' => 'boolean',
            'weight_max_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function bracket(): HasOne
    {
        return $this->hasOne(Bracket::class);
    }

    /**
     * Nama kelas yang berdiri sendiri tanpa konteks di sekitarnya.
     *
     * "Kelas A" saja ambigu: ada Kelas A putra dan Kelas A putri di tiap
     * golongan usia. Di layar, kolom sebelahnya sering sudah menyebutkan
     * golongan dan jenis kelaminnya — tapi di cetakan jadwal dan ekspor CSV
     * tidak, sehingga dua baris berbeda tampak identik.
     */
    public function namaLengkap(): string
    {
        // Kelas yang belum lengkap atributnya tetap punya nama; yang hilang
        // hanya keterangan tambahannya, bukan seluruh barisnya.
        return collect([
            $this->jenis_kelamin?->label(),
            $this->golongan_usia?->label(),
            $this->name,
        ])->filter()->implode(' ');
    }

    /**
     * Pendaftaran yang masuk ke kelas ini.
     *
     * Dipakai membedakan kelas yang benar-benar dipertandingkan dari kelas
     * yang cuma ada di katalog: naskah aturan 2025 memuat 174 kelas, dan satu
     * kejuaraan hanya menjalankan sebagian kecilnya.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Apakah berat badan ini masuk kelas.
     *
     * Inklusivitas tiap batas dibaca dari barisnya, bukan diasumsikan — naskah
     * memakai tiga rumusan berbeda ("Diatas ... sampai", "... sampai ...",
     * "Dibawah ..."). Lihat catatan di migrasinya.
     */
    public function memuatBerat(float $kg): bool
    {
        if ($this->weight_min !== null) {
            $bawah = (float) $this->weight_min;
            $lolosBawah = $this->weight_min_exclusive ? $kg > $bawah : $kg >= $bawah;

            if (! $lolosBawah) {
                return false;
            }
        }

        if ($this->weight_max !== null) {
            $atas = (float) $this->weight_max;
            $lolosAtas = $this->weight_max_inclusive ? $kg <= $atas : $kg < $atas;

            if (! $lolosAtas) {
                return false;
            }
        }

        return true;
    }

    /** Rentang berat sebagaimana dibaca panitia dan petugas timbang. */
    public function rentang(): string
    {
        $min = $this->weight_min === null ? null : rtrim(rtrim((string) $this->weight_min, '0'), '.');
        $max = $this->weight_max === null ? null : rtrim(rtrim((string) $this->weight_max, '0'), '.');

        return match (true) {
            $min === null && $max === null => 'Tanpa batas berat',
            $min === null => "Di bawah {$max} kg",
            $max === null => "Di atas {$min} kg",
            $this->weight_min_exclusive => "Di atas {$min} kg sampai {$max} kg",
            default => "{$min} kg sampai {$max} kg",
        };
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
