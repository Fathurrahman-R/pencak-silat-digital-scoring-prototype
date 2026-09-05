<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu tempat di bagan Jurus. Peserta kosong berarti bye. */
class JurusBracketSlot extends Model
{
    use HasFactory;

    protected $fillable = ['jurus_bracket_id', 'position', 'registration_id'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(JurusBracket::class, 'jurus_bracket_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
