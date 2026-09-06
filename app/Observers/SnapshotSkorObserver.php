<?php

namespace App\Observers;

use App\Models\Penalty;
use App\Models\ScoreEvent;
use App\Models\SilatMatch;
use App\Support\Scoring\SnapshotSkor;

/**
 * Membuang snapshot skor begitu bahan hitungannya berubah.
 *
 * # Kenapa observer, bukan panggilan di tiap tempat
 *
 * Nilai dan hukuman lahir, berubah, dan dibatalkan dari banyak arah:
 * konsensus juri (ConsensusEvaluator), jatuhan mutlak Dewan Wasit Juri
 * (PartaiScoringController), tangga hukuman wasit (TanggaHukuman), hasil
 * verifikasi juri (PollingVerifikasi), dan pembatalan lewat `voided_at` di
 * beberapa jalur berbeda. Menitipkan "jangan lupa buang snapshotnya" ke tiap
 * pemanggil berarti menunggu satu di antaranya lupa -- dan yang lupa tidak
 * akan terlihat sebagai galat. Yang terlihat cuma skor yang berhenti bergerak
 * setelah dewan juri membatalkan sesuatu, di tengah pertandingan, tepat pada
 * angka yang sedang disengketakan.
 *
 * Observer menutup semua jalur itu sekaligus, termasuk jalur yang belum
 * ditulis.
 *
 * # Kenapa `deleted` ikut didengarkan
 *
 * Pembatalan yang normal memakai `voided_at`, bukan penghapusan baris. Tapi
 * cascade dari penghapusan partai dan pembersihan data uji tetap menghapus
 * baris sungguhan, dan snapshot yang tertinggal sesudahnya menyebut skor dari
 * nilai yang sudah tidak ada.
 */
class SnapshotSkorObserver
{
    public function __construct(private readonly SnapshotSkor $snapshot) {}

    public function saved(ScoreEvent|Penalty $baris): void
    {
        $this->buang($baris);
    }

    public function deleted(ScoreEvent|Penalty $baris): void
    {
        $this->buang($baris);
    }

    private function buang(ScoreEvent|Penalty $baris): void
    {
        /*
         * Partainya diambil tanpa relasi supaya observer tidak menyeret
         * bracket, kelas, dan turnamen ke dalam transaksi penerbitan nilai.
         * Yang dibutuhkan cuma satu baris untuk mengosongkan satu penanda.
         */
        $match = $baris->relationLoaded('match')
            ? $baris->match
            : SilatMatch::query()->find($baris->match_id);

        if ($match !== null) {
            $this->snapshot->batalkan($match);
        }
    }
}
