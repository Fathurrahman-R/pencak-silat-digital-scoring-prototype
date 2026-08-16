<?php

namespace App\Models;

use App\Enums\JenisBerkas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'athlete_id',
        'jenis',
        'path',
        'original_name',
        'size_bytes',
        'mime',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisBerkas::class,
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Ukuran berkas dalam bentuk yang terbaca orang. */
    public function ukuran(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb < 1024
            ? round($kb).' KB'
            : round($kb / 1024, 1).' MB';
    }
}
