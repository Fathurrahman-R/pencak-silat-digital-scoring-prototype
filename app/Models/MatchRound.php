<?php

namespace App\Models;

use App\Enums\StatusBabak;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * State timer satu babak. Waktu resminya dihitung dari kolom-kolom ini, tidak
 * pernah dari jam perangkat mana pun -- lihat App\Support\Scoring\MatchTimer.
 */
class MatchRound extends Model
{
    use HasFactory;

    /** Lihat catatan yang sama di App\Models\JudgeInput. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'match_id',
        'round',
        'duration_ms',
        'started_at',
        'paused_at',
        'accumulated_ms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'accumulated_ms' => 'integer',
            'status' => StatusBabak::class,
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function berjalan(): bool
    {
        return $this->status === StatusBabak::Berjalan;
    }

    /**
     * Milidetik yang sudah terpakai sampai saat ini.
     *
     * Selagi berjalan, waktu terpakai bertambah sejak `started_at`. Selagi
     * jeda atau belum mulai, ia diam di `accumulated_ms` -- itulah gunanya
     * kolom ini dipisah dari perhitungan waktu berjalan.
     */
    public function waktuTerpakaiMs(): int
    {
        if (! $this->berjalan() || $this->started_at === null) {
            return $this->accumulated_ms;
        }

        return $this->accumulated_ms + $this->started_at->diffInMilliseconds(now());
    }

    public function sisaMs(): int
    {
        return max(0, $this->duration_ms - $this->waktuTerpakaiMs());
    }

    public function habis(): bool
    {
        return $this->sisaMs() <= 0;
    }
}
