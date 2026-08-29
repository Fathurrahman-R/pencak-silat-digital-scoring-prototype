<?php

namespace App\Models;

use App\Enums\JawabanVerifikasi;
use App\Enums\JenisVerifikasi;
use App\Enums\TingkatPelanggaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pertanyaan verifikasi dari Wasit atau Ketua Pertandingan ke tiga juri
 * -- Pasal 13.
 *
 * Hasilnya tidak pernah disunting. Verifikasi yang keliru dibatalkan lewat
 * status `dibatalkan`, dan nilai atau hukuman yang telanjur terbit darinya
 * dibatalkan dengan mekanismenya sendiri -- sama seperti koreksi dewan juri
 * pada ScoreEvent.
 */
class JudgeVerification extends Model
{
    use HasFactory;

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    /** Sama dengan JudgeInput: jawaban juri dibedakan sampai milidetik. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'match_id',
        'round',
        'jenis',
        'tingkat_pelanggaran',
        'score_event_id',
        'penalty_id',
        'diminta_oleh',
        'diminta_at',
        'status',
        'hasil',
        'hasil_at',
        'diterapkan_at',
        'diterapkan_oleh',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'jenis' => JenisVerifikasi::class,
            'tingkat_pelanggaran' => TingkatPelanggaran::class,
            'hasil' => JawabanVerifikasi::class,
            'diminta_at' => 'datetime',
            'hasil_at' => 'datetime',
            'diterapkan_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(JudgeVerificationAnswer::class);
    }

    public function peminta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diminta_oleh');
    }

    public function penerap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterapkan_oleh');
    }

    public function scoreEvent(): BelongsTo
    {
        return $this->belongsTo(ScoreEvent::class);
    }

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    public function berjalan(): bool
    {
        return $this->status === self::BERJALAN;
    }

    public function sudahDiterapkan(): bool
    {
        return $this->diterapkan_at !== null;
    }

    /**
     * Verifikasi yang sedang menahan pertandingan.
     *
     * Dipakai di jalur terpanas: tiap penyegaran state panel menanyakan ini.
     */
    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->where('status', self::BERJALAN);
    }
}
