<?php

namespace App\Models;

use App\Enums\Sudut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu insiden hitungan wasit terhadap pesilat yang jatuh -- Pasal 11.6.g.2/3. */
class TechnicalCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'round',
        'corner',
        'count_reached',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'corner' => Sudut::class,
            'count_reached' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SilatMatch::class, 'match_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
