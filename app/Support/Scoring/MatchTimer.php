<?php

namespace App\Support\Scoring;

use App\Enums\AkibatProtes;
use App\Enums\StatusBabak;
use App\Models\MatchRound;
use App\Models\Registration;
use App\Models\SilatMatch;
use App\Support\Bagan\PromosiPemenang;
use RuntimeException;

/**
 * Timer babak server-authoritative -- Pasal 9 dan 11.
 *
 * Waktu resmi selalu dihitung dari `started_at`/`accumulated_ms` di server,
 * tidak pernah dari jam perangkat operator atau juri. Panel operator hanya
 * menampilkan hasil hitungan ini; ia tidak diberi wewenang menghitung
 * sendiri.
 */
class MatchTimer
{
    public function mulaiBabak(SilatMatch $match, int $babak): MatchRound
    {
        if ($match->selesai()) {
            throw new RuntimeException('Partai ini sudah selesai.');
        }

        $this->pastikanTidakAdaSusulan($match);

        if ($babak > 1 && ! $this->babakSebelumnyaSelesai($match, $babak)) {
            throw new RuntimeException("Babak ".($babak - 1)." belum selesai.");
        }

        $round = MatchRound::firstOrNew(['match_id' => $match->id, 'round' => $babak]);

        if ($round->exists && $round->status !== StatusBabak::BelumMulai) {
            throw new RuntimeException("Babak {$babak} sudah dimulai.");
        }

        if (! $round->exists) {
            $round->duration_ms = $this->durasiBabak($match, $babak);
            $round->status = StatusBabak::BelumMulai;
        }

        $round->fill([
            'status' => StatusBabak::Berjalan,
            'started_at' => now(),
            'paused_at' => null,
        ])->save();

        $match->update(['current_round' => $babak, 'status' => SilatMatch::STATUS_BERLANGSUNG]);

        return $round->refresh();
    }

    public function jeda(MatchRound $round): MatchRound
    {
        if (! $round->berjalan()) {
            throw new RuntimeException('Babak ini tidak sedang berjalan.');
        }

        $round->update([
            'accumulated_ms' => $round->waktuTerpakaiMs(),
            'status' => StatusBabak::Jeda,
            'started_at' => null,
            'paused_at' => now(),
        ]);

        return $round->refresh();
    }

    public function lanjutkan(MatchRound $round): MatchRound
    {
        $this->pastikanTidakAdaSusulan($round->match);

        if ($round->status !== StatusBabak::Jeda) {
            throw new RuntimeException('Babak ini tidak sedang jeda.');
        }

        $round->update([
            'status' => StatusBabak::Berjalan,
            'started_at' => now(),
            'paused_at' => null,
        ]);

        return $round->refresh();
    }

    /** Mengembalikan babak ke keadaan semula -- dipakai saat operator salah memulai. */
    public function reset(MatchRound $round): MatchRound
    {
        $round->update([
            'status' => StatusBabak::BelumMulai,
            'started_at' => null,
            'paused_at' => null,
            'accumulated_ms' => 0,
        ]);

        return $round->refresh();
    }

    public function selesaikanBabak(MatchRound $round): MatchRound
    {
        $round->update([
            'accumulated_ms' => $round->waktuTerpakaiMs(),
            'status' => StatusBabak::Selesai,
            'started_at' => null,
        ]);

        return $round->refresh();
    }

    /**
     * Mengakhiri partai dengan sebab tertentu (KO, TKO, WMP, mutlak, undur
     * diri, cedera, WO, diskualifikasi, atau menang angka lewat pemecah
     * seri) dan langsung menaikkan pemenangnya ke partai berikutnya di
     * bagan -- menutup satu-satunya bagian promosi otomatis yang sebelumnya
     * hanya berjalan untuk bye.
     */
    public function akhiriPartai(SilatMatch $match, Registration $pemenang, string $sebab): SilatMatch
    {
        $babakAktif = $match->babakAktif();

        /*
         * Babak yang sedang DIJEDA ikut ditutup, bukan cuma yang berjalan.
         *
         * KO, undur diri, dan diskualifikasi hampir selalu diputuskan saat jam
         * sudah dijeda -- itu justru urutan yang benar. Kalau babaknya
         * dibiarkan berstatus 'jeda', partai yang sudah punya pemenang tetap
         * terbaca sebagai babak yang bisa dilanjutkan: tombol Lanjutkan, Jeda,
         * Selesaikan babak, dan Reset babak tetap hidup di panel operator.
         */
        if (in_array($babakAktif?->status, [StatusBabak::Berjalan, StatusBabak::Jeda], true)) {
            $this->selesaikanBabak($babakAktif);
        }

        $match->update([
            'status' => SilatMatch::STATUS_SELESAI,
            'winner_registration_id' => $pemenang->id,
            'win_reason' => $sebab,
        ]);

        (new PromosiPemenang)($match->refresh());

        return $match->refresh();
    }

    private function babakSebelumnyaSelesai(SilatMatch $match, int $babak): bool
    {
        return $match->rounds()
            ->where('round', $babak - 1)
            ->where('status', StatusBabak::Selesai)
            ->exists();
    }

    /**
     * Apakah partai ini berhak atas satu babak tambahan.
     *
     * Dua jalan, keduanya dari naskah:
     *   - seri yang tidak terpecah sampai tiga kriteria pertama (Pasal 11.6.g.1.b.3)
     *   - protes manajer diterima dengan akibat babak tambahan (Pasal 15.4.c.e.2)
     *
     * Yang pertama sudah lama dikenal `pemecah_seri`; yang kedua baru.
     */
    private function babakTambahanDiizinkan(SilatMatch $match): bool
    {
        return $match->managerProtests()
            ->where('keputusan', 'diterima')
            ->where('akibat', AkibatProtes::BabakTambahan->value)
            ->exists();
    }

    /**
     * Jam babak berjalan tidak boleh hidup selagi babak lain dibuka untuk
     * susulan.
     *
     * Kalau dibiarkan: waktu babak berjalan terus berkurang, sementara SELURUH
     * input dikunci ke babak susulan -- CatatInputJuri menolak babak berjalan,
     * dan guard hukuman melakukan hal yang sama. Yang tersisa adalah menit-menit
     * pertandingan yang habis tanpa seorang pun bisa mencatat apa pun di
     * dalamnya, dan waktu itu tidak bisa dikembalikan.
     *
     * Ditegakkan di sini, bukan cuma disembunyikan tombolnya: panel bukan
     * satu-satunya jalan masuk ke endpoint timer.
     *
     * @throws RuntimeException
     */
    private function pastikanTidakAdaSusulan(SilatMatch $match): void
    {
        if ($match->susulan_round !== null) {
            throw new RuntimeException(
                "Babak {$match->susulan_round} sedang dibuka untuk input susulan — tutup dulu sebelum menjalankan babak.",
            );
        }
    }

    private function durasiBabak(SilatMatch $match, int $babak): int
    {
        $golongan = $match->bracket->weightClass->golongan_usia;
        $peraturan = $match->bracket->weightClass->tournament->peraturan();
        $setelan = $peraturan->babakUntuk($golongan);

        /*
         * Satu babak MELEBIHI jumlah golongan usia hanya dibuka lewat satu
         * jalan: protes manajer yang diterima dengan akibat "menambah
         * pertandingan 1 (satu) babak" (Pasal 15 ayat 4 huruf c.e.2).
         *
         * Batasnya tetap tegas -- satu babak, bukan berapa pun. Melonggarkan
         * `jumlah` di setelan peraturan akan membuka babak tambahan untuk
         * SELURUH partai, termasuk yang tidak pernah diprotes.
         */
        if ($babak > $setelan['jumlah'] + ($this->babakTambahanDiizinkan($match) ? 1 : 0)) {
            throw new RuntimeException(
                "Golongan {$golongan->label()} hanya punya {$setelan['jumlah']} babak.",
            );
        }

        return $setelan['durasi_ms'];
    }
}
