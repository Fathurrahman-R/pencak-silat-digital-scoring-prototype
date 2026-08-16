<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use App\Enums\JenisBerkas;
use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Athlete extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contingent_id',
        'name',
        'jenis_kelamin',
        'birth_date',
        'weight_claim',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'birth_date' => 'date',
            'weight_claim' => 'decimal:1',
        ];
    }

    public function contingent(): BelongsTo
    {
        return $this->belongsTo(Contingent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RegistrationDocument::class);
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(Registration::class, 'registration_athlete')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * Umur pada bulan kejuaraan dimulai — Pasal 2.
     *
     * Bukan umur hari ini. Anak yang berulang tahun antara pendaftaran dan
     * hari bertanding bisa berpindah golongan, dan yang berlaku adalah
     * golongannya saat bertanding.
     */
    public function umurSaatKejuaraan(?Tournament $tournament = null): int
    {
        $acuan = ($tournament ?? $this->contingent->tournament)->starts_on ?? now();

        return $this->birth_date->diffInYears($acuan);
    }

    public function golonganUsia(?Tournament $tournament = null): ?GolonganUsia
    {
        return GolonganUsia::untukUmur($this->umurSaatKejuaraan($tournament));
    }

    /**
     * Berkas yang wajib ada sebelum pendaftaran atlet ini boleh diajukan.
     *
     * Surat pernyataan tidak hamil hanya diwajibkan bagi pesilat putri
     * golongan Remaja dan Dewasa (Pasal 2 ayat 5 huruf c), jadi daftarnya
     * berbeda antar atlet dan tidak bisa ditulis sebagai satu daftar tetap.
     *
     * @return list<JenisBerkas>
     */
    public function berkasWajib(?Tournament $tournament = null): array
    {
        $wajib = JenisBerkas::wajibUmum();

        $golongan = $this->golonganUsia($tournament);

        if ($this->jenis_kelamin === JenisKelamin::Putri
            && in_array($golongan, [GolonganUsia::Remaja, GolonganUsia::Dewasa], strict: true)) {
            $wajib[] = JenisBerkas::SuratTidakHamil;
        }

        return $wajib;
    }

    /** @return list<JenisBerkas> berkas wajib yang belum diunggah */
    public function berkasKurang(?Tournament $tournament = null): array
    {
        $ada = $this->documents->pluck('jenis')->all();

        return array_values(array_filter(
            $this->berkasWajib($tournament),
            static fn (JenisBerkas $jenis): bool => ! in_array($jenis, $ada, strict: true),
        ));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return blank($term) ? $query : $query->where('name', 'like', "%{$term}%");
    }
}
