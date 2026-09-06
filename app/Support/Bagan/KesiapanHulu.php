<?php

namespace App\Support\Bagan;

use App\Models\SilatMatch;
use App\Support\Sinkron\Kepemilikan;
use Illuminate\Support\Collection;

/**
 * Apakah partai ini boleh dijalankan, dilihat dari partai yang mengisinya.
 *
 * # Persoalan yang ditutupnya
 *
 * Bagan tidak berhenti di batas gelanggang. Pemenang perdelapan final di
 * gelanggang A naik ke perempat final yang bisa saja dijadwalkan di gelanggang
 * B, dan di model peer-to-peer laptop B belum tentu tahu siapa pemenangnya --
 * pertukaran datanya baru terjadi kalau ada yang menekan tombol sinkron.
 *
 * Tanpa penjagaan, pengendali gelanggang B menayangkan partai yang salah satu
 * sudutnya kosong, memanggil pesilat yang tidak ada, dan baru sadar setelah
 * gong pertama. Yang lebih buruk: ia mengisi sudut itu sendiri dengan tebakan.
 *
 * # Letak partai hulu dihitung, bukan disimpan
 *
 * PromosiPemenang sudah menetapkan aritmetikanya dari arah maju: pemenang
 * partai nomor p babak r naik ke partai ceil(p/2) babak r+1. Dibalik, partai
 * nomor p babak r diisi oleh partai 2p-1 dan 2p dari babak r-1. Menyimpannya
 * sebagai kolom hanya menambah satu hal yang bisa bertentangan dengan
 * kenyataan.
 *
 * # Yang TIDAK dijaga
 *
 * Partai hulu yang berjalan di gelanggang INI tidak diperiksa. Datanya sudah
 * ada di laptop yang sama, dan kalau belum disahkan, itu urusan alur
 * pertandingan biasa -- bukan urusan sinkronisasi. Menjaganya di sini berarti
 * menolak partai karena alasan yang pesannya akan menyesatkan.
 */
class KesiapanHulu
{
    public function __construct(private readonly Kepemilikan $kepemilikan) {}

    public function siap(SilatMatch $match): bool
    {
        return $this->belumSiap($match)->isEmpty();
    }

    /**
     * Partai hulu di gelanggang lain yang hasilnya belum sampai ke sini.
     *
     * @return Collection<int, SilatMatch>
     */
    public function belumSiap(SilatMatch $match): Collection
    {
        // Babak pertama tidak punya hulu: pesertanya datang dari undian,
        // bukan dari partai sebelumnya.
        if ($match->round <= 1 || $match->bracket_id === null) {
            return collect();
        }

        return SilatMatch::query()
            ->where('bracket_id', $match->bracket_id)
            ->where('round', $match->round - 1)
            ->whereIn('position', [$match->position * 2 - 1, $match->position * 2])
            ->get()
            ->filter(fn (SilatMatch $hulu) => $this->menungguGelanggangLain($hulu))
            ->values();
    }

    /**
     * Nama gelanggang yang hasilnya ditunggu, untuk disebut di pesan galat.
     *
     * Pesan yang cuma bilang "data belum lengkap" memaksa pengendali menebak
     * harus menarik dari mana. Di tengah hari pertandingan, tebakan itu
     * dijawab dengan menekan semua tombol sinkron satu per satu.
     *
     * @return list<string>
     */
    public function gelanggangDitunggu(SilatMatch $match): array
    {
        return $this->belumSiap($match)
            ->map(fn (SilatMatch $hulu) => $hulu->arena?->name ?? 'gelanggang lain')
            ->unique()
            ->values()
            ->all();
    }

    private function menungguGelanggangLain(SilatMatch $hulu): bool
    {
        /*
         * Partai hulu yang belum dijadwalkan ke gelanggang mana pun juga
         * belum bisa dimainkan, jadi hasilnya memang belum ada di mana-mana.
         * Ia bukan urusan sinkronisasi.
         */
        if ($hulu->arena === null) {
            return false;
        }

        if ($this->kepemilikan->memegangArena($hulu->arena->code)) {
            return false;
        }

        // Disahkan berarti hasilnya sudah final DAN sudah sampai ke sini --
        // ratified_at yang terisi di basis data ini hanya bisa datang lewat
        // sinkron, karena partainya berjalan di laptop lain.
        return $hulu->ratified_at === null;
    }
}
