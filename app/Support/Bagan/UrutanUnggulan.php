<?php

namespace App\Support\Bagan;

/**
 * Urutan tempat pada bagan gugur tunggal.
 *
 * Menghasilkan susunan nomor unggulan sedemikian rupa sehingga unggulan
 * teratas baru bertemu di babak paling akhir: pada bagan 8 tempat, urutannya
 * 1-8-5-4-3-6-7-2. Ini susunan baku yang dipakai hampir semua cabang olahraga
 * bersistem gugur.
 *
 * Kegunaannya di sini bukan soal unggulan — kejuaraan pencak silat daerah
 * lazim mengundi tanpa seeding. Yang dipakai adalah sifat lain dari susunan
 * ini: kalau tempat yang tersisa diisi bye, byenya tersebar merata ke seluruh
 * bagan, bukan menumpuk di satu sisi. Tanpa itu, separuh bagan bisa melenggang
 * ke semifinal tanpa bertanding sekali pun sementara separuh lainnya harus
 * melewati tiga partai.
 */
final class UrutanUnggulan
{
    /**
     * Susunan nomor unggulan untuk bagan berukuran $ukuran.
     *
     * @return list<int> panjangnya $ukuran, isinya 1..$ukuran tanpa berulang
     */
    public static function untuk(int $ukuran): array
    {
        if ($ukuran < 1 || ($ukuran & ($ukuran - 1)) !== 0) {
            throw new \InvalidArgumentException(
                "Ukuran bagan harus pangkat dua, diberikan {$ukuran}.",
            );
        }

        $urutan = [1];

        // Tiap penggandaan, sisipkan pasangan tiap nomor: yang menjumlahkan
        // dirinya dengan lawannya menjadi (ukuran babak itu + 1).
        while (count($urutan) < $ukuran) {
            $lawan = count($urutan) * 2 + 1;
            $baru = [];

            foreach ($urutan as $nomor) {
                $baru[] = $nomor;
                $baru[] = $lawan - $nomor;
            }

            $urutan = $baru;
        }

        return $urutan;
    }

    /** Ukuran bagan terkecil yang memuat $peserta orang. */
    public static function ukuranBagan(int $peserta): int
    {
        if ($peserta < 2) {
            throw new \InvalidArgumentException(
                'Bagan butuh sekurang-kurangnya dua peserta.',
            );
        }

        $ukuran = 2;

        while ($ukuran < $peserta) {
            $ukuran *= 2;
        }

        return $ukuran;
    }
}
