<?php

namespace App\Models;

use App\Enums\Sudut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu tinjauan VAR -- Pasal 15. */
class VarReview extends Model
{
    use HasFactory;

    public const SAH = 'sah';

    public const TIDAK_SAH = 'tidak_sah';

    protected $fillable = [
        'match_id',
        'protest_card_id',
        'round',
        'corner',
        'kejadian',
        'score_event_id',
        'penalty_id',
        'diajukan_at',
        'diajukan_oleh',
        'tenggat_at',
        'diputuskan_at',
        'keputusan',
        'diputuskan_oleh',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'corner' => Sudut::class,
            'diajukan_at' => 'datetime',
            'tenggat_at' => 'datetime',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function protestCard(): BelongsTo
    {
        return $this->belongsTo(ProtestCard::class);
    }

    public function scoreEvent(): BelongsTo
    {
        return $this->belongsTo(ScoreEvent::class);
    }

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function sudahDiputuskan(): bool
    {
        return $this->diputuskan_at !== null;
    }

    /**
     * Lewat tenggat 5 menit -- naskah mengarahkan prosesnya ke verifikasi
     * juri yang dipimpin Ketua Pertandingan, bukan membatalkan protesnya.
     * Dipakai panel untuk menampilkan peringatan, bukan mengunci tombol.
     */
    public function lewatTenggat(): bool
    {
        return ! $this->sudahDiputuskan() && now()->isAfter($this->tenggat_at);
    }

    public function sisaDetik(): int
    {
        return max(0, $this->tenggat_at->getTimestamp() - now()->getTimestamp());
    }
}
