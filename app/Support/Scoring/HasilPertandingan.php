<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;

/**
 * Hasil perhitungan menang angka.
 *
 * `pemenang` bisa `null` meski `sebab` sudah terisi -- itu berarti kelima
 * tingkat pemecah seri sudah dijalankan tapi belum ada yang memisahkan
 * (butuh babak tambahan) atau langkah terakhirnya memang menuntut tindakan
 * manusia (undian oleh Ketua Pertandingan). Ini keputusan yang sengaja tidak
 * diotomatisi.
 */
final class HasilPertandingan
{
    public function __construct(
        public readonly ?Sudut $pemenang,
        public readonly string $sebab,
        public readonly int $skorMerah,
        public readonly int $skorBiru,
    ) {}
}
