<?php

namespace App\Support\Bagan;

use App\Models\Arena;
use App\Models\SilatMatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menempatkan partai ke gelanggang dan menjaga urutan tayangnya.
 *
 * Jadwal di sini adalah URUTAN TAYANG, bukan jam. Pertandingan pencak silat
 * molor karena protes, verifikasi juri, dan cedera; jam yang dicetak pagi
 * hari sudah meleset sebelum gelanggang kedua selesai babak pertama, dan
 * jadwal yang jamnya meleset lebih menyesatkan daripada jadwal yang tidak
 * menyebut jam sama sekali. Yang menandai partai sedang dipakai adalah
 * statusnya, bukan jamnya.
 */
class PenjadwalPartai
{
    public function tetapkan(SilatMatch $match, Arena $arena): SilatMatch
    {
        if (! $match->siapDipertandingkan()) {
            throw new RuntimeException('Partai ini belum punya dua peserta — belum bisa dijadwalkan.');
        }

        if ($match->selesai()) {
            throw new RuntimeException('Partai yang sudah selesai tidak bisa dijadwalkan ulang.');
        }

        $this->pastikanBelumDimulai($match, 'dijadwalkan ulang');

        $urutanTerakhir = SilatMatch::where('arena_id', $arena->id)->max('order_in_arena');

        $match->update([
            'arena_id' => $arena->id,
            'order_in_arena' => ((int) $urutanTerakhir) + 1,
        ]);

        return $match->refresh();
    }

    public function lepas(SilatMatch $match): SilatMatch
    {
        /*
         * Melepas partai yang sudah dimulai mendamparkannya. Kewenangan
         * operator diikat ke gelanggang partai, jadi partai tanpa gelanggang
         * tidak bisa dijeda, diselesaikan babaknya, maupun diakhiri oleh
         * siapa pun -- sementara papan skor publik gelanggang itu mendadak
         * kosong di tengah pertandingan.
         *
         * Partai yang sudah selesai juga ditahan: gelanggang dan urutan
         * tayangnya bagian dari catatan hasil yang masuk berita acara.
         */
        $this->pastikanBelumDimulai($match, 'dilepas dari gelanggangnya');
        $this->pastikanTidakSedangDitayangkan($match);

        $match->update(['arena_id' => null, 'order_in_arena' => null]);

        return $match->refresh();
    }

    /**
     * Jadwal hanya boleh disentuh selama partainya belum dimulai.
     *
     * Pesannya menyebut aksinya, bukan kalimat umum — panitia yang menyusun
     * jadwal pagi hari perlu tahu partai mana yang sudah tidak bisa digeser
     * dan kenapa.
     */
    private function pastikanBelumDimulai(SilatMatch $match, string $aksi): void
    {
        if ($match->status === SilatMatch::STATUS_BERLANGSUNG) {
            throw new RuntimeException("Partai ini sedang berlangsung — tidak bisa {$aksi}. Akhiri dulu partainya.");
        }

        if ($match->selesai()) {
            throw new RuntimeException("Partai ini sudah selesai — tidak bisa {$aksi}.");
        }
    }

    /**
     * Partai yang sedang ditunjuk gelanggang tidak boleh dilepas.
     *
     * Statusnya boleh saja masih `terjadwal` -- pengendali sudah memilihnya
     * sebagai partai berikutnya, papan tampilan gelanggang dan overlay siaran
     * sudah menampilkan nama kedua pesilat, tapi babaknya belum ditekan mulai.
     * Melepasnya di detik itu membuat pointer menunjuk partai tanpa gelanggang,
     * dan tayangannya jatuh kembali ke turunan lama: partai lain, tanpa satu
     * pun pesan yang menjelaskan kenapa.
     */
    private function pastikanTidakSedangDitayangkan(SilatMatch $match): void
    {
        $ditayangkan = Arena::whereKey($match->arena_id)
            ->menayangkanPartai($match->id)
            ->exists();

        if ($ditayangkan) {
            throw new RuntimeException(
                'Partai ini sedang ditayangkan di gelanggangnya — pindahkan dulu partai aktif gelanggang itu.',
            );
        }
    }

    /** Menukar urutan tayang partai dengan tetangganya dalam gelanggang yang sama. */
    /**
     * Memindahkan partai ke urutan tertentu, menggeser yang lain sekali jalan.
     *
     * Bedanya dengan urutkan(): yang itu menukar dengan tetangga sebelah, satu
     * langkah per panggilan. Memindahkan partai dari urutan 14 ke urutan 2
     * berarti dua belas panggilan, masing-masing satu permintaan HTTP dan satu
     * pemuatan ulang halaman -- dan panitia yang menyusun jadwal pagi hari
     * melakukannya berkali-kali untuk gelanggang yang isinya empat puluh
     * partai.
     */
    public function pindahkan(SilatMatch $match, int $tujuan): SilatMatch
    {
        if ($match->arena_id === null) {
            throw new RuntimeException('Partai ini belum dijadwalkan ke gelanggang mana pun.');
        }

        $this->pastikanBelumDimulai($match, 'digeser urutannya');

        return DB::transaction(function () use ($match, $tujuan) {
            $urut = SilatMatch::where('arena_id', $match->arena_id)
                ->orderBy('order_in_arena')
                ->lockForUpdate()
                ->get();

            $tujuan = max(1, min($tujuan, $urut->count()));

            $tanpa = $urut->reject(fn (SilatMatch $m) => $m->id === $match->id)->values();
            $baru = $tanpa->splice(0, $tujuan - 1)
                ->push($match)
                ->concat($tanpa)
                ->values();

            /*
             * Ditulis ulang seluruhnya, bukan cuma yang bergeser. Menghitung
             * mana saja yang berubah menghemat beberapa UPDATE dan membuka
             * celah nomor ganda kalau urutan awalnya sudah tidak rapat --
             * yang terjadi tiap kali satu partai dilepas dari jadwal.
             */
            foreach ($baru as $i => $m) {
                if ($m->order_in_arena !== $i + 1) {
                    $m->update(['order_in_arena' => $i + 1]);
                }
            }

            return $match->refresh();
        });
    }

    public function urutkan(SilatMatch $match, int $langkah): SilatMatch
    {
        if ($match->arena_id === null) {
            throw new RuntimeException('Partai ini belum dijadwalkan ke gelanggang mana pun.');
        }

        $this->pastikanBelumDimulai($match, 'digeser urutannya');

        $tetangga = SilatMatch::where('arena_id', $match->arena_id)
            ->where('order_in_arena', $match->order_in_arena + $langkah)
            ->first();

        if ($tetangga === null) {
            return $match;
        }

        DB::transaction(function () use ($match, $tetangga) {
            [$urutanMatch, $urutanTetangga] = [$match->order_in_arena, $tetangga->order_in_arena];

            $match->update(['order_in_arena' => $urutanTetangga]);
            $tetangga->update(['order_in_arena' => $urutanMatch]);
        });

        return $match->refresh();
    }
}
