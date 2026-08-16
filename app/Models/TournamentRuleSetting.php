<?php

namespace App\Models;

use App\Enums\GolonganUsia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentRuleSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'jumlah_juri_tanding',
        'ambang_sepakat',
        'window_konsensus_ms',
        'jumlah_juri_jurus',
        'istirahat_ms',
        'nilai',
        'hukuman',
        'babak',
        'wmp_selisih',
        'kartu_protes_tanding',
        'kartu_protes_jurus',
        'tenggat_var_detik',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'array',
            'hukuman' => 'array',
            'babak' => 'array',
            'wmp_selisih' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Nilai bawaan dari naskah 2025, siap dipakai membuat baris baru.
     *
     * Dibaca sekali saat kejuaraan dibuat, lalu tidak pernah dibaca lagi —
     * sesudah itu baris inilah yang berlaku. Lihat catatan di migrasinya.
     *
     * @return array<string, mixed>
     */
    public static function bawaan(): array
    {
        return [
            'jumlah_juri_tanding' => config('scoring.juri.tanding.jumlah'),
            'ambang_sepakat' => config('scoring.juri.tanding.ambang_sepakat'),
            'window_konsensus_ms' => config('scoring.juri.tanding.window_ms'),
            'jumlah_juri_jurus' => config('scoring.juri.jurus.jumlah_minimal'),
            'istirahat_ms' => config('scoring.tanding.istirahat_ms'),
            'nilai' => config('scoring.tanding.nilai'),
            'hukuman' => config('scoring.tanding.hukuman'),
            'babak' => config('scoring.tanding.babak'),
            'wmp_selisih' => config('scoring.tanding.wmp_selisih'),
            'kartu_protes_tanding' => config('scoring.var.kartu_protes.tanding'),
            'kartu_protes_jurus' => config('scoring.var.kartu_protes.jurus'),
            'tenggat_var_detik' => config('scoring.var.tenggat_keputusan_detik'),
        ];
    }

    /**
     * Jumlah dan durasi babak untuk satu golongan usia — Pasal 11 ayat 3.
     *
     * @return array{jumlah: int, durasi_ms: int}
     */
    public function babakUntuk(GolonganUsia $golongan): array
    {
        return $this->babak[$golongan->value]
            ?? throw new \RuntimeException("Golongan [{$golongan->value}] tidak punya setelan babak.");
    }

    /**
     * Ambang selisih nilai untuk menang mutlak — Pasal 11.6.g.4.b.
     *
     * @return array{selisih: int, mulai_babak: int}
     */
    public function wmpUntuk(GolonganUsia $golongan): array
    {
        return $this->wmp_selisih[$golongan->value] ?? $this->wmp_selisih['bawaan'];
    }

    public function nilaiUntuk(string $jenisSerangan): int
    {
        return $this->nilai[$jenisSerangan]
            ?? throw new \RuntimeException("Jenis serangan [{$jenisSerangan}] tidak bernilai.");
    }

    /**
     * Ambang sepakat tidak boleh melebihi jumlah juri — kalau itu terjadi,
     * tidak ada nilai yang bisa terbit sama sekali dan pertandingan berjalan
     * dengan papan skor yang diam terus.
     */
    public function ambangMasukAkal(): bool
    {
        return $this->ambang_sepakat >= 1
            && $this->ambang_sepakat <= $this->jumlah_juri_tanding;
    }
}
