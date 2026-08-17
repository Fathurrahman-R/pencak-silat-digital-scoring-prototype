<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nilai satu juri untuk satu penampilan -- skala 9.00-10.00. */
class JurusScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_id',
        'judge_user_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(JurusPerformance::class, 'performance_id');
    }

    public function juri(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_user_id');
    }
}
