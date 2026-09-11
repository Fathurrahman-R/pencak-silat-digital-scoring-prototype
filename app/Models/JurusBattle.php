<?php

namespace App\Models;

use App\Support\Bagan\Contracts\Terbagankan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pertemuan dua sudut di bagan Jurus.
 *
 * Sejajar dengan SilatMatch, dan memenuhi kontrak Terbagankan yang sama --
 * PromosiPemenang menaikkan pemenangnya dengan aritmetika yang persis sama.
 *
 * Bedanya dengan Tanding: tidak ada babak, tidak ada timer babak, dan nilainya
 * tidak terbit dari konsensus juri melainkan dari median seluruh juri atas dua
 * penampilan terpisah.
 */
class JurusBattle extends Model implements Terbagankan
{
    use HasFactory;

    public const STATUS_TERJADWAL = 'terjadwal';

    public const STATUS_BERLANGSUNG = 'berlangsung';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'jurus_bracket_id',
        'round',
        'position',
        'red_registration_id',
        'blue_registration_id',
        'winner_registration_id',
        'win_reason',
        'keputusan_alasan',
        'keputusan_oleh',
        'status',
        'arena_id',
        'order_in_arena',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'position' => 'integer',
            'order_in_arena' => 'integer',
            'scheduled_at' => 'datetime',
        ];
    }

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(JurusBracket::class, 'jurus_bracket_id');
    }

    public function red(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'red_registration_id');
    }

    public function blue(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'blue_registration_id');
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    /** Ketua Pertandingan yang memutuskan battle berskor seri. */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keputusan_oleh');
    }

    /**
     * Battle babak pertama yang lawannya tidak akan pernah ada.
     *
     * Definisinya sama persis dengan SilatMatch::bye(): tepat satu sudut
     * berpenghuni di ronde pertama. Dipakai PohonBagan untuk menandai slot yang
     * melenggang tanpa bertanding.
     */
    public function bye(): bool
    {
        return $this->round === 1
            && count(array_filter([$this->red_registration_id, $this->blue_registration_id])) === 1;
    }

    /** Dua penampilan yang menyusun battle ini -- satu per sudut. */
    public function performances(): HasMany
    {
        return $this->hasMany(JurusPerformance::class, 'jurus_battle_id');
    }

    public function baganPartai(): BelongsTo
    {
        return $this->bracket();
    }

    public function sesamaBagan(): HasMany
    {
        return $this->bracket->battles();
    }

    public function posisiBerikutnya(): int
    {
        return (int) ceil($this->position / 2);
    }

    /** Pemenang partai ganjil masuk sudut merah, yang genap masuk sudut biru. */
    public function sudutBerikutnya(): string
    {
        return $this->position % 2 === 1 ? 'red' : 'blue';
    }

    public function selesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }
}
