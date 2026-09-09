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

/** Bagan gugur satu nomor Jurus berformat battle. Cermin Bracket untuk Tanding. */
class JurusBracket extends Model implements SumberBagan
{
    use HasFactory, MenamaiBabak;

    protected $fillable = ['jurus_event_id', 'size', 'locked_at', 'locked_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'locked_at' => 'datetime'];
    }

    public function jurusEvent(): BelongsTo
    {
        return $this->belongsTo(JurusEvent::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(JurusBracketSlot::class)->orderBy('position');
    }

    public function battles(): HasMany
    {
        return $this->hasMany(JurusBattle::class)->orderBy('round')->orderBy('position');
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
     * Kontrak SumberBagan, dibaca PohonBagan -- pohon yang sama persis dengan
     * bagan Tanding. Bagan yang digambar ulang di tempat lain akan menyimpang
     * diam-diam, dan pohon yang garisnya meleset menyesatkan pembacanya tentang
     * siapa bertemu siapa.
     */

    public function ukuranBagan(): int
    {
        return (int) $this->size;
    }

    /**
     * Selalu gugur -- naskah menyebutnya harfiah, Pasal 12.1.b.1.
     *
     * Konstanta, BUKAN kolom. Kolom yang hanya pernah berisi satu nilai adalah
     * kolom yang suatu saat diisi nilai kedua tanpa ada satu baris kode pun
     * yang menanganinya.
     */
    public function modeBagan(): ModeBagan
    {
        return ModeBagan::Gugur;
    }

    /** @return Collection<int, Model> */
    public function tempatBagan(): Collection
    {
        return $this->slots;
    }

    /** @return Collection<int, Model> */
    public function partaiBagan(): Collection
    {
        return $this->battles;
    }
}
