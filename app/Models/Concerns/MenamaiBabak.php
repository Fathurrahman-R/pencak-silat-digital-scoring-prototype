<?php

namespace App\Models\Concerns;

/**
 * Penamaan babak bagan gugur, dipakai bersama Bracket dan JurusBracket.
 *
 * Digunakan bersama, bukan disalin: dua match-expression yang harus tetap sama
 * adalah dua yang suatu saat berbeda, dan bagan yang menyebut "Semifinal" di
 * layar panitia sementara siarannya menyebut "Penyisihan 2" membuat tiga pihak
 * membaca undian yang sama dengan cara berbeda.
 */
trait MenamaiBabak
{
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
        return (int) ceil(log(max(2, $this->ukuranBagan()), 2));
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
