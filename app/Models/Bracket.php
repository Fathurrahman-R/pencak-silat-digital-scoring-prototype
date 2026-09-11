<?php

namespace App\Models;

use App\Enums\ModeBagan;
use App\Models\Concerns\MenamaiBabak;
use App\Support\Bagan\Contracts\SumberBagan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Bracket extends Model implements SumberBagan
{
    use HasFactory, MenamaiBabak;

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

    /*
     * Kontrak SumberBagan, dibaca PohonBagan.
     *
     * Mengembalikan relasi yang SUDAH dimuat, bukan menjalankan kuerinya lagi:
     * BracketController::show() memuatnya lewat with(), dan menjalankan ulang
     * di sini akan menambah dua query pada tiap permukaan yang menggambar
     * bagan -- termasuk overlay siaran yang ditarik terus-menerus.
     */

    public function ukuranBagan(): int
    {
        return (int) $this->size;
    }

    public function modeBagan(): ModeBagan
    {
        return $this->mode ?? ModeBagan::Gugur;
    }

    /** @return Collection<int, Model> */
    public function tempatBagan(): Collection
    {
        return $this->slots;
    }

    /** @return Collection<int, Model> */
    public function partaiBagan(): Collection
    {
        return $this->matches;
    }
}
