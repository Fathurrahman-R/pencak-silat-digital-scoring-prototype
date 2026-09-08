<?php

namespace App\Models;

use App\Enums\Sudut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jatah kartu protes satu sudut pada satu partai -- Pasal 15.2.a. */
class ProtestCard extends Model
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
        'corner',
        'jumlah_dipakai',
    ];

    protected function casts(): array
    {
        return [
            'corner' => Sudut::class,
            'jumlah_dipakai' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function varReviews(): HasMany
    {
        return $this->hasMany(VarReview::class);
    }

    /**
     * Sisa kartu protes sudut ini.
     *
     * Jatahnya dari setelan kejuaraan; config cuma dipakai kalau partainya
     * belum termuat -- baris kartu bisa dibaca lepas dari partainya, dan satu
     * kueri rantai penuh untuk mengisi angka di layar tidak sepadan.
     */
    public function sisaKartu(): int
    {
        $jatah = $this->match?->bracket?->weightClass?->tournament?->peraturan()?->kartu_protes_tanding
            ?? config('scoring.var.kartu_protes.tanding');

        return max(0, (int) $jatah - $this->jumlah_dipakai);
    }
}
