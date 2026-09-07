<?php

namespace App\Models;

use App\Enums\ModeBagan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'weight_class_id',
        'size',
        'mode',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'mode' => ModeBagan::class,
            'locked_at' => 'datetime',
        ];
    }

    public function weightClass(): BelongsTo
    {
        return $this->belongsTo(WeightClass::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class)->orderBy('position');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SilatMatch::class)->orderBy('round')->orderBy('position');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function terkunci(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Berapa babak dari babak pertama sampai final.
     *
     * Dibulatkan KE ATAS, bukan log2 apa adanya. Bagan gugur berukuran pangkat
     * dua tidak terpengaruh -- log2(16) tetap 4 -- tapi bagan pemasalan
     * berukuran 10 butuh empat babak (5 partai, 3, 2, 1), dan log2(10) yang
     * dipotong jadi 3 akan menghilangkan finalnya dari seluruh permukaan yang
     * menggambar bagan.
     */
    public function jumlahBabak(): int
    {
        return (int) ceil(log(max(2, $this->size), 2));
    }

    /**
     * Nama babak sebagaimana disebut panitia dan announcer.
     *
     * Dihitung mundur dari final, bukan maju dari babak pertama: yang dikenal
     * orang adalah "semifinal", bukan "babak ketiga".
     */
    public function namaBabak(int $round): string
    {
        $sisa = $this->jumlahBabak() - $round;

        return match ($sisa) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Perempat final',
            3 => 'Perdelapan final',
            default => 'Penyisihan '.$round,
        };
    }
}
