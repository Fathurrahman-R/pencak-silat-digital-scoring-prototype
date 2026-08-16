<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use App\Enums\KategoriPertandingan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeSchedule extends Model
{
    use HasFactory;

    public const KIND_NOMOR = 'nomor';

    public const KIND_KONTINGEN = 'kontingen';

    protected $fillable = [
        'tournament_id',
        'kind',
        'kategori',
        'golongan_usia',
        'amount',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriPertandingan::class,
            'golongan_usia' => GolonganUsia::class,
            'amount' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function scopeNomor(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_NOMOR);
    }

    public function scopeKontingen(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_KONTINGEN);
    }

    public function rupiah(): string
    {
        return 'Rp '.number_format($this->amount, 0, ',', '.');
    }

    public function keterangan(): string
    {
        if ($this->kind === self::KIND_KONTINGEN) {
            return 'Biaya tetap kontingen';
        }

        return trim(sprintf(
            '%s %s',
            $this->kategori?->label() ?? 'Semua kategori',
            $this->golongan_usia?->label() ?? 'semua golongan',
        ));
    }
}
