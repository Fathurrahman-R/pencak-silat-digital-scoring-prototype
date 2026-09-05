<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchOfficial extends Model
{
    use HasFactory;

    /*
     * Kunci ULID, bukan auto-increment.
     *
     * Tiap gelanggang menjalankan basis datanya sendiri, dan penghitung
     * auto-increment tiap basis data mulai dari satu. Dua gelanggang akan
     * menerbitkan baris bernomor sama, dan saat datanya digabungkan tidak ada
     * cara memilih di antara keduanya yang tidak membuang salah satu catatan
     * sungguhan.
     */
    use HasUlids;

    public const ROLE_WASIT = 'wasit';

    public const ROLE_JURI = 'juri';

    protected $fillable = [
        'match_id',
        'user_id',
        'role',
        'number',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sebutan(): string
    {
        return $this->role === self::ROLE_JURI && $this->number
            ? "Juri {$this->number}"
            : ucfirst($this->role);
    }
}
