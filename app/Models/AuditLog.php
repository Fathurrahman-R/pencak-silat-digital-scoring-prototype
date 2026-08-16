<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Jejak tindakan yang mengubah uang, skor, atau hasil pertandingan.
 *
 * Sengaja tidak punya `updated_at`: baris jejak tidak pernah disunting.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mencatat satu tindakan.
     *
     * Pelaku dan alamat IP diambil dari permintaan yang sedang berjalan, bukan
     * diterima sebagai argumen — supaya pemanggilnya tidak bisa keliru menulis
     * nama orang lain di jejak.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function catat(
        string $action,
        string $description,
        ?Model $auditable = null,
        array $properties = [],
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
