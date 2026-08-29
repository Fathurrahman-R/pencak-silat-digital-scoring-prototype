<?php

namespace App\Support\Bagan;

use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use Illuminate\Support\Collection;

/**
 * Siapa yang benar-benar bebas bertugas pada satu partai.
 *
 * Satu orang tidak bisa berdiri di dua gelanggang sekaligus. Sampai sekarang
 * tidak ada satu pun pemeriksaan yang menegakkan itu: daftar penugasan
 * menawarkan seluruh wasit dan juri terdaftar, panitia memilih orang yang
 * ternyata sedang memimpin partai di gelanggang sebelah, dan yang ketahuan
 * bukan sistemnya melainkan kursi juri yang kosong saat partai dimulai.
 *
 * Bentrok dihitung terhadap JADWAL, bukan terhadap keanggotaan. Aparat yang
 * ditugaskan di partai lain pada jam yang jauh sama sekali tidak bentrok —
 * satu orang memang memimpin banyak partai sepanjang hari.
 */
class KetersediaanAparat
{
    /**
     * Rentang bentrok, dalam menit sebelum dan sesudah jadwal partai.
     *
     * Satu partai Tanding berlangsung tiga babak dua menit, ditambah jeda,
     * persiapan, dan pengesahan hasil — pada praktiknya menghabiskan sekitar
     * dua puluh menit gelanggang. Empat puluh lima menit memberi kelonggaran
     * untuk partai yang molor karena protes atau verifikasi juri, tanpa
     * memblokir seluruh hari kerja aparat yang sama.
     */
    private const RENTANG_MENIT = 45;

    /**
     * Alasan tiap orang tidak bisa ditugaskan di partai ini, dipetakan per id.
     *
     * Yang TIDAK bentrok tidak muncul di hasil sama sekali — pemanggilnya
     * memeriksa keberadaan kunci, bukan nilai kosong.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, string>
     */
    public function bentrok(SilatMatch $match, Collection $userIds): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        $jadwal = $match->scheduled_at;

        $lain = MatchOfficial::query()
            ->whereIn('user_id', $userIds)
            ->where('match_id', '!=', $match->id)
            ->with(['match.arena:id,name'])
            ->get()
            ->filter(fn (MatchOfficial $o) => $this->berbenturan($o->match, $match, $jadwal));

        $alasan = [];

        foreach ($lain as $petugas) {
            // Yang pertama ditemukan cukup: panitia tidak butuh daftar seluruh
            // benturan, ia butuh tahu orang ini sedang dipakai di mana.
            $alasan[$petugas->user_id] ??= $this->kalimat($petugas);
        }

        return $alasan;
    }

    private function berbenturan(?SilatMatch $lain, SilatMatch $ini, $jadwal): bool
    {
        if ($lain === null || $lain->id === $ini->id) {
            return false;
        }

        // Partai yang sudah selesai tidak memakai siapa pun lagi.
        if ($lain->status === SilatMatch::STATUS_SELESAI) {
            return false;
        }

        // Yang sedang berlangsung selalu bentrok, berapa pun jadwal tertulisnya.
        if ($lain->status === SilatMatch::STATUS_BERLANGSUNG) {
            return true;
        }

        /*
         * Salah satu belum terjadwal: tidak ada dasar untuk menyatakan
         * bentrok, dan menolaknya akan menghalangi panitia menugaskan aparat
         * sebelum jadwalnya disusun -- urutan kerja yang justru lazim.
         */
        if ($jadwal === null || $lain->scheduled_at === null) {
            return false;
        }

        return $lain->scheduled_at->diffInMinutes($jadwal, absolute: true) < self::RENTANG_MENIT;
    }

    private function kalimat(MatchOfficial $petugas): string
    {
        $gelanggang = $petugas->match->arena?->name;
        $peran = $petugas->sebutan();

        if ($petugas->match->status === SilatMatch::STATUS_BERLANGSUNG) {
            return $gelanggang
                ? "{$peran} di {$gelanggang}, sedang berlangsung"
                : "{$peran} di partai yang sedang berlangsung";
        }

        $jam = $petugas->match->scheduled_at?->translatedFormat('H:i');

        return match (true) {
            $gelanggang !== null && $jam !== null => "{$peran} di {$gelanggang} jam {$jam}",
            $gelanggang !== null => "{$peran} di {$gelanggang}",
            default => "{$peran} di partai {$petugas->match_id}",
        };
    }
}
