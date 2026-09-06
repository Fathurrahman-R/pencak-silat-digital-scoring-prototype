<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali babak dibuka kembali untuk pencatatan susulan.
 *
 * Append-only. Barisnya tidak pernah dihapus dan `opened_at` tidak pernah
 * diubah -- yang ditulis belakangan hanya penutupnya.
 */
class MatchRoundReopen extends Model
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
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
