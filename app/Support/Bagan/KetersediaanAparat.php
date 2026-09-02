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
 * Bentrok dihitung terhadap STATUS partai lain, bukan terhadap keanggotaan.
 * Satu orang memang memimpin banyak partai sepanjang hari; yang mustahil
 * hanyalah memimpin dua partai yang sama-sama sedang berjalan.
 */
class KetersediaanAparat
{
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

        $lain = MatchOfficial::query()
            ->whereIn('user_id', $userIds)
            ->where('match_id', '!=', $match->id)
            ->with(['match.arena:id,name'])
            ->get()
            ->filter(fn (MatchOfficial $o) => $this->berbenturan($o->match, $match));

        $alasan = [];

        foreach ($lain as $petugas) {
            // Yang pertama ditemukan cukup: panitia tidak butuh daftar seluruh
            // benturan, ia butuh tahu orang ini sedang dipakai di mana.
            $alasan[$petugas->user_id] ??= $this->kalimat($petugas);
        }

        return $alasan;
    }

    /**
     * Bentrok dinyatakan dari STATUS partai lain, bukan dari jaraknya di jam.
     *
     * Sejak jadwal jadi urutan tayang tanpa jam, tidak ada lagi jarak yang
     * bisa dihitung. Yang tersisa satu-satunya keadaan yang benar-benar
     * mustahil: seorang aparat memimpin dua partai yang sama-sama berjalan.
     *
     * Partai yang baru terjadwal TIDAK dianggap bentrok. Panitia lazim
     * menugaskan aparat untuk sepuluh partai berikutnya sekaligus sebelum
     * satu pun dimulai, dan menolaknya berarti menghalangi urutan kerja yang
     * memang dipakai di gelanggang.
     */
    private function berbenturan(?SilatMatch $lain, SilatMatch $ini): bool
    {
        if ($lain === null || $lain->id === $ini->id) {
            return false;
        }

        return $lain->status === SilatMatch::STATUS_BERLANGSUNG;
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

        return match (true) {
            $gelanggang !== null => "{$peran} di {$gelanggang}, partai {$petugas->match_id}",
            default => "{$peran} di partai {$petugas->match_id}",
        };
    }
}
