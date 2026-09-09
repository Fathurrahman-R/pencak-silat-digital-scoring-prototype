<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Arena extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SilatMatch::class);
    }

    public function jurusPerformances(): HasMany
    {
        return $this->hasMany(JurusPerformance::class);
    }

    /**
     * Operator yang memegang gelanggang ini.
     *
     * Ditugaskan per gelanggang, bukan per partai: satu operator duduk di
     * satu gelanggang sepanjang hari, dan penugasannya ikut berlaku untuk
     * partai yang baru dijadwalkan ke sini kemudian.
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'arena_operators')->withTimestamps();
    }

    /**
     * Apa yang sedang ditayangkan gelanggang ini.
     *
     * Satu-satunya sumber kebenarannya. Sebelum ada catatan ini, ia diturunkan
     * dari `matches.status` -- yang berarti setiap panel ikut memutuskan
     * partai mana yang terbuka, dan memindahkannya menuntut partai berjalan
     * diakhiri lebih dulu.
     *
     * Ditulis HANYA oleh App\Support\Gelanggang\PointerTayang, sama
     * seperti `current_round` yang hanya ditulis MatchTimer.
     *
     * Tinggal di tabelnya sendiri, bukan sebagai kolom di sini: `arenas`
     * bergolongan GLOBAL di sinkron, dan pointer ini ditulis node gelanggang.
     * Lihat migrasi `buat_tabel_arena_tayang`.
     */
    public function tayang(): HasOne
    {
        return $this->hasOne(ArenaTayang::class);
    }

    public function partaiAktif(): HasOne
    {
        return $this->hasOne(ArenaTayang::class)->where('tayang_type', ArenaTayang::TANDING);
    }

    /**
     * Id partai Tanding yang sedang ditayangkan, atau null.
     *
     * Dipertahankan sebagai `active_match_id` supaya pemanggil yang sudah ada
     * tidak perlu ikut berubah saat pointernya pindah tabel. Ia membaca
     * relasi `tayang` -- eager-load relasi itu di jalur yang memuat banyak
     * gelanggang sekaligus, kalau tidak tiap gelanggang menambah satu query.
     */
    protected function activeMatchId(): Attribute
    {
        return Attribute::get(function (): ?int {
            $tayang = $this->tayang;

            return $tayang?->menayangkanPartai() ? (int) $tayang->tayang_id : null;
        });
    }

    protected function activeMatchSetAt(): Attribute
    {
        return Attribute::get(fn () => $this->tayang?->disetel_pada);
    }

    protected function activeMatchSetBy(): Attribute
    {
        return Attribute::get(fn () => $this->tayang?->disetel_oleh);
    }

    /**
     * Gelanggang yang sedang menayangkan sesuatu -- apa pun jenisnya.
     *
     * Menggantikan `whereNotNull('active_match_id')` yang tersebar di beberapa
     * tempat sebelum pointer pindah tabel.
     */
    public function scopeSedangTayang(Builder $query): Builder
    {
        return $query->whereHas('tayang', fn (Builder $t) => $t->whereNotNull('tayang_id'));
    }

    /** Gelanggang yang sedang menayangkan partai Tanding tertentu. */
    public function scopeMenayangkanPartai(Builder $query, int $matchId): Builder
    {
        return $query->whereHas('tayang', fn (Builder $t) => $t
            ->where('tayang_type', ArenaTayang::TANDING)
            ->where('tayang_id', $matchId));
    }

    /**
     * Gelanggang yang sedang menayangkan penampilan Jurus tertentu.
     *
     * Pasangan scopeMenayangkanPartai, bukan penggantinya: `tayang_id` menunjuk
     * dua tabel yang penomorannya berdiri sendiri, jadi id 7 pada `matches` dan
     * id 7 pada `jurus_performances` adalah dua hal berbeda. Menanyakannya
     * tanpa menyebut `tayang_type` akan menemukan gelanggang yang kebetulan
     * menayangkan partai bernomor sama -- dan penampilan yang sebenarnya bebas
     * akan ditolak dilepas dari jadwal, tanpa satu pun keterangan yang masuk
     * akal bagi yang membacanya.
     */
    public function scopeMenayangkanPenampilan(Builder $query, int $performanceId): Builder
    {
        return $query->whereHas('tayang', fn (Builder $t) => $t
            ->where('tayang_type', ArenaTayang::JURUS)
            ->where('tayang_id', $performanceId));
    }

    /**
     * Id partai yang sedang ditayangkan seluruh gelanggang pada satu kejuaraan.
     *
     * Satu query, bukan satu per gelanggang: dipakai di jalur yang menyusun
     * daftar, dan di sanalah selisihnya terasa.
     *
     * @return list<int>
     */
    public static function partaiYangSedangTayang(?int $tournamentId = null): array
    {
        return ArenaTayang::query()
            ->where('tayang_type', ArenaTayang::TANDING)
            ->whereNotNull('tayang_id')
            ->when($tournamentId !== null, fn (Builder $q) => $q
                ->whereHas('arena', fn (Builder $a) => $a->where('tournament_id', $tournamentId)))
            ->pluck('tayang_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Pengendali yang memegang gelanggang ini.
     *
     * Terpisah dari operators(): sejak timer dan perpindahan jadwal pindah ke
     * peran Pengendali Gelanggang, keduanya bukan orang yang sama lagi.
     * Operator menjalankan papan tampilan dan perangkat siaran; pengendali
     * memimpin jalannya partai.
     */
    public function pengendali(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'arena_pengendali')->withTimestamps();
    }

    /**
     * Aparat yang bertugas di gelanggang ini sepanjang hari.
     *
     * Disalin ke `match_officials` saat pengendali menunjuk sebuah partai --
     * lihat PointerTayang. Yang disimpan di sini penugasan hariannya;
     * yang tercatat di sana siapa yang sungguh bertugas pada partai itu.
     */
    public function aparat(): HasMany
    {
        return $this->hasMany(ArenaOfficial::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
