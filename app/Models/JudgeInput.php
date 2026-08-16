<?php

namespace App\Models;

use App\Enums\JenisSerangan;
use App\Enums\Sudut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu penekanan tombol juri, apa adanya.
 *
 * Tidak pernah diubah atau dihapus setelah tersimpan -- satu-satunya
 * transisi yang sah adalah mengisi `score_event_id` sekali, saat input ini
 * ikut membentuk nilai lewat ConsensusEvaluator.
 */
class JudgeInput extends Model
{
    use HasFactory;

    /*
     * Format bawaan Eloquent ('Y-m-d H:i:s') memangkas milidetik saat
     * menyimpan kolom bertipe datetime. Window konsensus 2 detik butuh
     * presisi milidetik, jadi format modelnya diperlebar supaya server_ts
     * tidak kehilangan presisi tepat saat ditulis ke database.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'match_id',
        'round',
        'judge_user_id',
        'corner',
        'point_type',
        'server_ts',
        'client_ts',
        'score_event_id',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'corner' => Sudut::class,
            'point_type' => JenisSerangan::class,
            'server_ts' => 'datetime',
            'client_ts' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_user_id');
    }

    public function ditolak(): bool
    {
        return $this->rejected_reason !== null;
    }

    public function sudahDipakai(): bool
    {
        return $this->score_event_id !== null;
    }
}
