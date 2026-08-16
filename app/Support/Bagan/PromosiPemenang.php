<?php

namespace App\Support\Bagan;

use App\Models\SilatMatch;
use RuntimeException;

/**
 * Menaikkan pemenang satu partai ke partai berikutnya.
 *
 * Letak tujuannya dihitung, bukan disimpan: pemenang partai nomor p babak r
 * selalu naik ke partai nomor ceil(p/2) babak r+1, menempati sudut merah bila
 * p ganjil dan sudut biru bila genap. Menyimpannya sebagai kolom hanya
 * menambah satu hal yang bisa bertentangan dengan kenyataan.
 *
 * Dipakai dua kali: saat bagan disusun untuk meluluskan bye, dan saat partai
 * sungguhan selesai.
 */
class PromosiPemenang
{
    public function __invoke(SilatMatch $partai): ?SilatMatch
    {
        if ($partai->winner_registration_id === null) {
            throw new RuntimeException(
                "Partai {$partai->id} belum punya pemenang, jadi tidak ada yang bisa dinaikkan.",
            );
        }

        // Final tidak punya partai berikutnya, dan itu bukan kesalahan.
        $berikutnya = $partai->bracket->matches()
            ->where('round', $partai->round + 1)
            ->where('position', $partai->posisiBerikutnya())
            ->first();

        if ($berikutnya === null) {
            return null;
        }

        $berikutnya->update([
            $partai->sudutBerikutnya().'_registration_id' => $partai->winner_registration_id,
        ]);

        return $berikutnya->refresh();
    }
}
