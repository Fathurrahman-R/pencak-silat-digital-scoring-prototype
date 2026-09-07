<?php

namespace App\Support\Bagan;

use App\Support\Bagan\Contracts\Terbagankan;
use Illuminate\Database\Eloquent\Model;
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
 *
 * Bertipe Terbagankan, bukan SilatMatch: aritmetika ini persis sama untuk
 * partai Tanding dan battle Jurus, dan menggandakannya berarti dua tempat yang
 * harus diperbaiki saat undian bergeser -- yang kedua akan tertinggal.
 */
class PromosiPemenang
{
    public function __invoke(Terbagankan&Model $partai): (Terbagankan&Model)|null
    {
        if ($partai->winner_registration_id === null) {
            throw new RuntimeException(
                "Partai {$partai->id} belum punya pemenang, jadi tidak ada yang bisa dinaikkan.",
            );
        }

        // Final tidak punya partai berikutnya, dan itu bukan kesalahan.
        $berikutnya = $partai->sesamaBagan()
            ->where('round', $partai->round + 1)
            ->where('position', $partai->posisiBerikutnya())
            ->first();

        if ($berikutnya === null) {
            return null;
        }

        $berikutnya->update([
            $partai->sudutBerikutnya().'_registration_id' => $partai->winner_registration_id,
        ]);

        $berikutnya->refresh();

        $this->rambatkanBilaTanpaLawan($berikutnya);

        return $berikutnya;
    }

    /**
     * Meneruskan peserta yang sudut lawannya tidak akan pernah terisi.
     *
     * Hanya terjadi pada bagan pemasalan berjumlah ganjil: partai terakhir
     * sebuah babak bisa berdiri tanpa partai pasangan di babak sebelumnya,
     * jadi tidak ada satu pun pemenang yang akan naik ke sudut satunya.
     * Membiarkannya berarti gelanggang menunggu lawan yang tidak ada, dan
     * itulah bentuk kemacetan yang paling sulit dikenali di hari-H: partai
     * terjadwal, papan siap, tapi satu sudut kosong selamanya.
     *
     * Untuk bagan pangkat dua, cabang ini tidak pernah diambil -- tiap partai
     * di babak dua ke atas selalu punya dua partai sumber -- jadi perilaku
     * bagan gugur dan battle Jurus tidak berubah sama sekali.
     */
    private function rambatkanBilaTanpaLawan(Terbagankan&Model $partai): void
    {
        if ($partai->winner_registration_id !== null) {
            return;
        }

        $terisi = $partai->red_registration_id ?? $partai->blue_registration_id;
        $kosong = $partai->red_registration_id === null || $partai->blue_registration_id === null;

        if ($terisi === null || ! $kosong) {
            return;
        }

        // Sudut yang kosong diisi pemenang partai sumbernya. Kalau partai
        // sumber itu tidak ada, tidak akan pernah ada yang mengisinya.
        $sumberKosong = $partai->red_registration_id === null
            ? $partai->position * 2 - 1
            : $partai->position * 2;

        $adaSumber = $partai->sesamaBagan()
            ->where('round', $partai->round - 1)
            ->where('position', $sumberKosong)
            ->exists();

        if ($adaSumber) {
            return;
        }

        $partai->update([
            'winner_registration_id' => $terisi,
            'win_reason' => 'bye',
            'status' => $partai::STATUS_SELESAI,
        ]);

        $this($partai->refresh());
    }
}
