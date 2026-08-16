<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'athlete_id',
        'weight',
        'passed',
        'weighed_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:1',
            'passed' => 'boolean',
            'weighed_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
