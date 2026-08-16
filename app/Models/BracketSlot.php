<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_id',
        'position',
        'registration_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** Tempat kosong berarti lawannya melaju tanpa bertanding. */
    public function bye(): bool
    {
        return $this->registration_id === null;
    }
}
