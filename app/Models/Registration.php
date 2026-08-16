<?php

namespace App\Models;

use App\Enums\KategoriPertandingan;
use App\Enums\StatusPendaftaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'contingent_id',
        'weight_class_id',
        'jurus_event_id',
        'status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPendaftaran::class,
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function contingent(): BelongsTo
    {
        return $this->belongsTo(Contingent::class);
    }

    public function weightClass(): BelongsTo
    {
        return $this->belongsTo(WeightClass::class);
    }

    public function jurusEvent(): BelongsTo
    {
        return $this->belongsTo(JurusEvent::class);
    }

    public function athletes(): BelongsToMany
    {
        // Nama tabel ditulis terang-terangan: kebiasaan Eloquent akan menebak
        // "athlete_registration", sedangkan tabelnya bernama sesuai urutan yang
        // dibaca manusia — pendaftaran dulu, baru atletnya.
        return $this->belongsToMany(Athlete::class, 'registration_athlete')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function weightIns(): HasMany
    {
        return $this->hasMany(WeightIn::class)->latest('weighed_at');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function kategori(): KategoriPertandingan
    {
        return $this->weight_class_id !== null
            ? KategoriPertandingan::Tanding
            : KategoriPertandingan::Jurus;
    }

    /** Nama nomor yang diikuti, sebagaimana dibaca panitia dan announcer. */
    public function namaNomor(): string
    {
        if ($this->weight_class_id !== null) {
            $kelas = $this->weightClass;

            return "Tanding {$kelas->jenis_kelamin->label()} {$kelas->golongan_usia->label()} {$kelas->name}";
        }

        return $this->jurusEvent->nama();
    }

    /** Berapa atlet yang seharusnya mengisi pendaftaran ini. */
    public function jumlahPesilatSeharusnya(): int
    {
        return $this->weight_class_id !== null
            ? 1
            : $this->jurusEvent->jenis->jumlahPesilat();
    }

    /**
     * Hasil timbang badan terakhir.
     *
     * Penimbangan ulang menghasilkan baris baru, dan yang berlaku adalah yang
     * terakhir — tetapi yang sebelumnya tetap tersimpan untuk diperiksa.
     */
    public function timbanganTerakhir(): ?WeightIn
    {
        return $this->weightIns()->first();
    }

    public function scopeSah(Builder $query): Builder
    {
        return $query->where('status', StatusPendaftaran::Terverifikasi);
    }

    public function scopeTanding(Builder $query): Builder
    {
        return $query->whereNotNull('weight_class_id');
    }

    public function scopeJurus(Builder $query): Builder
    {
        return $query->whereNotNull('jurus_event_id');
    }
}
