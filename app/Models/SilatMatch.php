<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu partai pada bagan.
 *
 * Bernama SilatMatch, bukan Match, karena `match` adalah kata kunci PHP sejak
 * 8.0 dan tidak bisa dipakai sebagai nama kelas. Nama tabelnya tetap `matches`.
 */
class SilatMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    public const STATUS_TERJADWAL = 'terjadwal';

    public const STATUS_BERLANGSUNG = 'berlangsung';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'bracket_id',
        'round',
        'position',
        'red_registration_id',
        'blue_registration_id',
        'winner_registration_id',
        'win_reason',
        'status',
        'current_round',
        'ratified_at',
        'ratified_by',
        'arena_id',
        'order_in_arena',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'position' => 'integer',
            'current_round' => 'integer',
            'ratified_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    public function red(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'red_registration_id');
    }

    public function blue(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'blue_registration_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'winner_registration_id');
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function officials(): HasMany
    {
        return $this->hasMany(MatchOfficial::class, 'match_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(MatchRound::class, 'match_id');
    }

    public function judgeInputs(): HasMany
    {
        return $this->hasMany(JudgeInput::class, 'match_id');
    }

    public function scoreEvents(): HasMany
    {
        return $this->hasMany(ScoreEvent::class, 'match_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class, 'match_id');
    }

    public function technicalCounts(): HasMany
    {
        return $this->hasMany(TechnicalCount::class, 'match_id');
    }

    public function ratifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratified_by');
    }

    /** Babak scoring (1/2/3) yang sedang berjalan -- bukan babak bagan. */
    public function babakAktif(): ?MatchRound
    {
        if ($this->current_round === null) {
            return null;
        }

        return $this->rounds()->where('round', $this->current_round)->first();
    }

    public function disahkan(): bool
    {
        return $this->ratified_at !== null;
    }

    /** Juri bertugas untuk partai ini, terurut nomor. */
    public function juriBertugas()
    {
        return $this->officials()->where('role', MatchOfficial::ROLE_JURI)->orderBy('number')->get();
    }

    public function wasitBertugas(): ?MatchOfficial
    {
        return $this->officials()->where('role', MatchOfficial::ROLE_WASIT)->first();
    }

    /** Kedua sudut terisi, jadi partainya memang akan dipertandingkan. */
    public function siapDipertandingkan(): bool
    {
        return $this->red_registration_id !== null && $this->blue_registration_id !== null;
    }

    /**
     * Hanya satu sudut yang terisi — lawannya bye.
     *
     * Berbeda dari partai yang belum lengkap: bye sudah pasti sejak bagan
     * disusun, sedangkan sudut yang menunggu pemenang babak sebelumnya belum
     * tentu kosong selamanya.
     */
    public function bye(): bool
    {
        return $this->round === 1
            && ($this->red_registration_id === null) !== ($this->blue_registration_id === null);
    }

    public function selesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    /** Nomor partai berikutnya yang menampung pemenang partai ini. */
    public function posisiBerikutnya(): int
    {
        return (int) ceil($this->position / 2);
    }

    /** Pemenang partai ganjil masuk sudut merah, yang genap masuk sudut biru. */
    public function sudutBerikutnya(): string
    {
        return $this->position % 2 === 1 ? 'red' : 'blue';
    }

    public function scopeBelumDijadwalkan(Builder $query): Builder
    {
        return $query->whereNull('arena_id');
    }

    public function scopeDiGelanggang(Builder $query, Arena $arena): Builder
    {
        return $query->where('arena_id', $arena->id)->orderBy('order_in_arena');
    }
}
