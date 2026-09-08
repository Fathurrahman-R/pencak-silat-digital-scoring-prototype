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
        'hitungan_teknik',
        'jurus_waktu',
        'override_golongan',
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
            'hitungan_teknik' => 'array',
            'jurus_waktu' => 'array',
            'override_golongan' => 'array',
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
            'hitungan_teknik' => config('scoring.tanding.hitungan_teknik'),
            'jurus_waktu' => [
                'toleransi_detik' => config('scoring.jurus.toleransi_detik'),
                'diskualifikasi_lewat_detik' => config('scoring.jurus.diskualifikasi_lewat_detik'),
            ],
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

    /**
     * Satu tahap tangga hukuman -- pembinaan, teguran, atau peringatan --
     * lengkap dengan bagian yang tidak disunting formulir.
     *
     * TIGA LAPIS, dari yang paling umum ke yang paling khusus:
     *
     *   1. angka naskah di `config/scoring.php`
     *   2. setelan kejuaraan ini
     *   3. pengecualian golongan usia yang bersangkutan
     *
     * Tiap lapis hanya menimpa kunci yang benar-benar diisinya. Baris yang
     * dibuat sebelum sebuah kunci diperkenalkan tidak memuat kunci itu, dan
     * tanpa penumpukan ini kejuaraan lama akan membaca `null` untuk ambang yang
     * menentukan kapan hukuman naik tingkat -- yang berarti tangga hukumannya
     * berhenti bekerja tanpa satu pun pesan galat.
     *
     * `$golongan` null berarti setelan umum kejuaraan, tanpa pengecualian --
     * dipakai formulir setelan, bukan oleh penegakan di gelanggang.
     *
     * @return array<string, mixed>
     */
    public function hukumanTahap(string $tahap, ?GolonganUsia $golongan = null): array
    {
        return array_replace(
            (array) config("scoring.tanding.hukuman.{$tahap}"),
            (array) ($this->hukuman[$tahap] ?? []),
            (array) ($this->pengecualian($golongan)['hukuman'][$tahap] ?? []),
        );
    }

    /**
     * Ambang hitungan teknik -- Pasal 11.6.g.2 dan 11.6.g.3.
     *
     * Termasuk `cakupan_beruntun`: apakah hitungan beruntun terhadap pesilat
     * yang jatuh dihitung per babak atau sepanjang partai.
     *
     * @return array<string, mixed>
     */
    public function hitunganTeknik(?GolonganUsia $golongan = null): array
    {
        return array_replace(
            (array) config('scoring.tanding.hitungan_teknik'),
            (array) ($this->hitungan_teknik ?? []),
            (array) ($this->pengecualian($golongan)['hitungan_teknik'] ?? []),
        );
    }

    /**
     * Pengecualian tersimpan untuk satu golongan usia.
     *
     * @return array<string, mixed>
     */
    public function pengecualian(?GolonganUsia $golongan): array
    {
        if ($golongan === null) {
            return [];
        }

        return (array) ($this->override_golongan[$golongan->value] ?? []);
    }

    /** Apakah golongan ini punya setelan yang menyimpang dari setelan umum. */
    public function punyaPengecualian(GolonganUsia $golongan): bool
    {
        return $this->pengecualian($golongan) !== [];
    }

    /**
     * Toleransi waktu penampilan Jurus satu golongan usia -- Pasal 12.
     *
     * Golongan yang tidak punya angkanya sendiri memakai baris 'bawaan', pola
     * yang sama dengan wmpUntuk().
     *
     * @return array{toleransi_detik: int, diskualifikasi_lewat_detik: int}
     */
    public function jurusWaktuUntuk(GolonganUsia $golongan): array
    {
        $setelan = array_replace(
            [
                'toleransi_detik' => (array) config('scoring.jurus.toleransi_detik'),
                'diskualifikasi_lewat_detik' => (array) config('scoring.jurus.diskualifikasi_lewat_detik'),
            ],
            (array) ($this->jurus_waktu ?? []),
        );

        return [
            'toleransi_detik' => (int) ($setelan['toleransi_detik'][$golongan->value]
                ?? $setelan['toleransi_detik']['bawaan']),
            'diskualifikasi_lewat_detik' => (int) ($setelan['diskualifikasi_lewat_detik'][$golongan->value]
                ?? $setelan['diskualifikasi_lewat_detik']['bawaan']),
        ];
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
