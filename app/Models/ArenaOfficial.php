<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan aparat ke satu gelanggang, berlaku sepanjang hari.
 *
 * Sengaja memakai nama peran yang sama persis dengan MatchOfficial: barisnya
 * disalin apa adanya ke sana saat pengendali menunjuk sebuah partai, dan
 * pemetaan nama peran di tengah penyalinan adalah tempat yang bagus untuk
 * menaruh bug yang tidak terlihat sampai hari-H.
 */
class ArenaOfficial extends Model
{
    use HasFactory;

    protected $fillable = [
        'arena_id',
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

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
