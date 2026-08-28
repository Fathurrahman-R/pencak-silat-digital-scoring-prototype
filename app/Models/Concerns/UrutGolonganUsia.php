<?php

namespace App\Models\Concerns;

use App\Enums\GolonganUsia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mengurutkan menurut UMUR, bukan alfabet.
 *
 * `orderBy('golongan_usia')` mengurutkan nilai backing enum sebagai teks,
 * sehingga hasilnya: dewasa, master_1, master_2, pra_remaja, pra_usia_dini,
 * remaja, usia_dini_1, usia_dini_2. Golongan Dewasa muncul paling atas dan
 * Usia Dini paling bawah — kebalikan dari urutan yang dipakai buku acara,
 * papan pengumuman, dan cara panitia menyebut golongan di lapangan.
 *
 * Urutan yang benar sudah tercatat sebagai urutan case di App\Enums\GolonganUsia,
 * mengikuti Pasal 2 Peraturan Pertandingan 2025. Trait ini memakai urutan itu.
 *
 * Pengurutan dikerjakan di basis data, bukan setelah get(), supaya masih benar
 * saat dipasangkan dengan limit atau paginate.
 */
trait UrutGolonganUsia
{
    public function scopeUrutGolonganUsia(Builder $query, string $kolom = 'golongan_usia'): Builder
    {
        $kolom = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($kolom));

        $cases = GolonganUsia::cases();
        $kapan = str_repeat("WHEN ? THEN ? ", count($cases));

        $ikatan = [];
        foreach ($cases as $i => $golongan) {
            $ikatan[] = $golongan->value;
            $ikatan[] = $i;
        }

        return $query->orderByRaw("CASE {$kolom} {$kapan}ELSE ? END", [...$ikatan, count($cases)]);
    }
}
