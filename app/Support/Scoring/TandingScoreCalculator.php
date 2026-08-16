<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;
use App\Models\SilatMatch;

/**
 * Skor kumulatif seluruh babak dan penentuan menang angka -- Pasal 11.6.g.1.
 *
 * Skor tidak pernah disimpan sebagai kolom. Ia dihitung ulang dari
 * score_events dan penalties setiap kali dibutuhkan, sehingga koreksi dewan
 * juri lewat pembatalan salah satu baris langsung berlaku tanpa perlu
 * menyunting angka tersimpan di tempat lain.
 */
class TandingScoreCalculator
{
    /** Nilai prestasi teknik dikurangi hukuman, dijumlah dari seluruh babak. */
    public function skor(SilatMatch $match, Sudut $sudut): int
    {
        $nilai = (int) $match->scoreEvents()->berlaku()->where('corner', $sudut)->sum('value');
        $hukuman = (int) $match->penalties()->berlaku()->where('corner', $sudut)->sum('points');

        return $nilai + $hukuman;
    }

    /** Skor pada satu babak saja -- dipakai memantau jalannya babak, bukan hasil akhir. */
    public function skorBabak(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        $nilai = (int) $match->scoreEvents()->berlaku()->where('corner', $sudut)->where('round', $babak)->sum('value');
        $hukuman = (int) $match->penalties()->berlaku()->where('corner', $sudut)->where('round', $babak)->sum('points');

        return $nilai + $hukuman;
    }

    /**
     * Sudut yang berhak ditawari menang WMP -- Pasal 11.6.g.4.b. Ini sinyal
     * untuk ditawarkan ke operator, bukan keputusan yang otomatis mengakhiri
     * partai; operator yang menekan tombol akhiri.
     */
    public function cekTawaranWmp(SilatMatch $match): ?Sudut
    {
        $golongan = $match->bracket->weightClass->golongan_usia;
        $setelan = $match->bracket->weightClass->tournament->peraturan()->wmpUntuk($golongan);

        $babak = $match->current_round ?? 1;

        if ($babak < $setelan['mulai_babak']) {
            return null;
        }

        $merah = $this->skor($match, Sudut::Merah);
        $biru = $this->skor($match, Sudut::Biru);

        if (abs($merah - $biru) < $setelan['selisih']) {
            return null;
        }

        return $merah > $biru ? Sudut::Merah : Sudut::Biru;
    }

    /**
     * Menentukan pemenang menang angka, menjalankan pemecah seri berurutan
     * kalau skornya sama persis.
     */
    public function tentukanPemenangAngka(SilatMatch $match): HasilPertandingan
    {
        $merah = $this->skor($match, Sudut::Merah);
        $biru = $this->skor($match, Sudut::Biru);

        if ($merah !== $biru) {
            return new HasilPertandingan($merah > $biru ? Sudut::Merah : Sudut::Biru, 'angka', $merah, $biru);
        }

        foreach (config('scoring.tanding.pemecah_seri') as $kriteria) {
            /*
             * Babak tambahan bukan sesuatu yang bisa dihitung dari data yang
             * ada -- ia baru "selesai dicoba" kalau babak ekstra itu memang
             * sudah dimainkan (rounds bertambah melebihi jumlah normal
             * golongan ini) dan skornya masih tetap sama. Selama belum
             * dicoba, inilah titik berhentinya.
             */
            if ($kriteria === 'babak_tambahan') {
                if (! $this->sudahMenjalaniBabakTambahan($match)) {
                    return new HasilPertandingan(null, 'perlu_babak_tambahan', $merah, $biru);
                }

                continue;
            }

            if ($kriteria === 'undian') {
                return new HasilPertandingan(null, 'perlu_undian', $merah, $biru);
            }

            $pemenang = match ($kriteria) {
                'hukuman_terendah' => $this->pemecahHukumanTerendah($match),
                'nilai_prestasi_tertinggi' => $this->pemecahNilaiTertinggi($match),
                'berat_badan_teringan' => $this->pemecahBeratBadan($match),
                default => null,
            };

            if ($pemenang !== null) {
                return new HasilPertandingan($pemenang, $kriteria, $merah, $biru);
            }
        }

        return new HasilPertandingan(null, 'perlu_undian', $merah, $biru);
    }

    private function pemecahHukumanTerendah(SilatMatch $match): ?Sudut
    {
        $merah = (int) $match->penalties()->berlaku()->where('corner', Sudut::Merah)->sum('points');
        $biru = (int) $match->penalties()->berlaku()->where('corner', Sudut::Biru)->sum('points');

        if ($merah === $biru) {
            return null;
        }

        // points bernilai negatif -- yang jumlahnya lebih dekat ke nol berarti hukumannya lebih rendah.
        return $merah > $biru ? Sudut::Merah : Sudut::Biru;
    }

    private function pemecahNilaiTertinggi(SilatMatch $match): ?Sudut
    {
        foreach ([3, 2, 1] as $nilai) {
            $merah = $match->scoreEvents()->berlaku()->where('corner', Sudut::Merah)->where('value', $nilai)->count();
            $biru = $match->scoreEvents()->berlaku()->where('corner', Sudut::Biru)->where('value', $nilai)->count();

            if ($merah !== $biru) {
                return $merah > $biru ? Sudut::Merah : Sudut::Biru;
            }
        }

        return null;
    }

    private function sudahMenjalaniBabakTambahan(SilatMatch $match): bool
    {
        $golongan = $match->bracket->weightClass->golongan_usia;
        $jumlahNormal = $match->bracket->weightClass->tournament->peraturan()->babakUntuk($golongan)['jumlah'];

        return $match->rounds()->where('round', '>', $jumlahNormal)->exists();
    }

    private function pemecahBeratBadan(SilatMatch $match): ?Sudut
    {
        $beratMerah = $match->red?->timbanganTerakhir()?->weight;
        $beratBiru = $match->blue?->timbanganTerakhir()?->weight;

        if ($beratMerah === null || $beratBiru === null || (float) $beratMerah === (float) $beratBiru) {
            return null;
        }

        return $beratMerah < $beratBiru ? Sudut::Merah : Sudut::Biru;
    }
}
