<?php

namespace App\Models;

use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Enums\TingkatPelanggaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu sanksi -- Pasal 11.6.d.4: pembinaan, teguran, atau peringatan. */
class Penalty extends Model
{
    use HasFactory;

    /*
     * Kunci ULID, bukan auto-increment. Tiap gelanggang menjalankan basis
     * datanya sendiri, dan penghitung auto-increment tiap basis data mulai
     * dari satu -- dua gelanggang akan menerbitkan baris bernomor sama.
     */
    use HasUlids;

    protected $fillable = [
        'match_id',
        'round',
        'corner',
        'tier',
        'level',
        'points',
        'violation_level',
        'note',
        'created_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'corner' => Sudut::class,
            'tier' => TingkatHukuman::class,
            'level' => 'integer',
            'points' => 'integer',
            'violation_level' => TingkatPelanggaran::class,
            'voided_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function dibatalkan(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Peringatan III -- tidak ada pengurangan nilai, tapi berarti diskualifikasi.
     *
     * Tingkatnya dari setelan kejuaraan, dengan config sebagai jaring pengaman
     * saat baris ini dibaca lepas dari partainya (riwayat, ekspor). Yang
     * menegakkan diskualifikasi tetap TanggaHukuman; metode ini cuma menamai
     * baris yang sudah tercatat.
     */
    public function diskualifikasi(): bool
    {
        $tingkat = $this->match?->bracket?->weightClass?->tournament?->peraturan()
            ?->hukumanTahap('peringatan')['tingkat_diskualifikasi']
            ?? config('scoring.tanding.hukuman.peringatan.tingkat_diskualifikasi');

        return $this->tier === TingkatHukuman::Peringatan && $this->level >= (int) $tingkat;
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
