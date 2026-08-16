<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Enums\TingkatPelanggaran;
use App\Models\Penalty;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tangga hukuman -- Pasal 11.6.d.4: Pembinaan, Teguran, Peringatan,
 * Diskualifikasi. Ditegakkan di sisi server supaya wasit tidak bisa keliru
 * menjatuhkan tahap yang salah, dan supaya panel manapun yang dipakai
 * (operator, wasit di tablet terpisah) selalu tunduk pada aturan yang sama.
 *
 * Tiap tahap dihitung dari baris `penalties` yang sudah tercatat, bukan dari
 * penghitung tersimpan -- selaras dengan pola "tidak menyimpan yang bisa
 * dihitung" yang dipakai golongan usia dan posisi bagan berikutnya.
 *
 * Pembinaan berlaku sepanjang partai tapi TERSETEL ULANG setiap kali ia
 * mendorong eskalasi ke Teguran -- naskah menyebut pembinaan "masih boleh
 * diberikan" lagi setelah Peringatan dijatuhkan, dan itu hanya masuk akal
 * kalau hitungannya memang mulai dari nol lagi setelah eskalasi terakhir.
 * Teguran dihitung per babak (cakupan `babak`), sehingga otomatis mulai
 * dari nol tiap babak baru. Peringatan berlaku sepanjang partai dan tidak
 * pernah mereset.
 */
class TanggaHukuman
{
    public function __construct(private readonly MatchTimer $timer) {}

    /**
     * @throws RuntimeException bila pesilat ini sudah didiskualifikasi
     */
    public function catat(
        SilatMatch $match,
        Sudut $sudut,
        int $babak,
        TingkatPelanggaran $tingkat,
        ?string $catatan,
        User $pencatat,
    ): Penalty {
        if ($this->sudahDiskualifikasi($match, $sudut)) {
            throw new RuntimeException('Pesilat ini sudah didiskualifikasi.');
        }

        return DB::transaction(fn () => match ($tingkat) {
            TingkatPelanggaran::Ringan => $this->tanganiRingan($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
            TingkatPelanggaran::Sedang => $this->jatuhkanTeguran($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
            TingkatPelanggaran::Berat => $this->jatuhkanPeringatan($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
        });
    }

    private function tanganiRingan(
        SilatMatch $match,
        Sudut $sudut,
        int $babak,
        TingkatPelanggaran $tingkat,
        ?string $catatan,
        User $pencatat,
    ): Penalty {
        $terpakai = $this->jumlahPembinaan($match, $sudut);
        $ambang = config('scoring.tanding.hukuman.pembinaan.ambang_naik_ke_teguran');

        if ($terpakai < $ambang) {
            return Penalty::create([
                'match_id' => $match->id,
                'round' => $babak,
                'corner' => $sudut,
                'tier' => TingkatHukuman::Pembinaan,
                'level' => $terpakai + 1,
                'points' => config('scoring.tanding.hukuman.pembinaan.pengurangan'),
                'violation_level' => $tingkat,
                'note' => $catatan,
                'created_by' => $pencatat->id,
            ]);
        }

        // Sudah dua kali pembinaan -- pelanggaran ringan berikutnya dipaksa naik jadi Teguran.
        return $this->jatuhkanTeguran($match, $sudut, $babak, $tingkat, $catatan, $pencatat);
    }

    private function jatuhkanTeguran(
        SilatMatch $match,
        Sudut $sudut,
        int $babak,
        TingkatPelanggaran $tingkat,
        ?string $catatan,
        User $pencatat,
    ): Penalty {
        $terpakai = $this->jumlahTeguran($match, $sudut, $babak);
        $ambangNaik = config('scoring.tanding.hukuman.teguran.naik_ke_peringatan_pada');
        $levelBaru = $terpakai + 1;

        if ($levelBaru >= $ambangNaik) {
            // Teguran ketiga tidak pernah tercatat sebagai teguran.
            return $this->jatuhkanPeringatan($match, $sudut, $babak, $tingkat, $catatan, $pencatat);
        }

        return Penalty::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'tier' => TingkatHukuman::Teguran,
            'level' => $levelBaru,
            'points' => config("scoring.tanding.hukuman.teguran.pengurangan.{$levelBaru}"),
            'violation_level' => $tingkat,
            'note' => $catatan,
            'created_by' => $pencatat->id,
        ]);
    }

    private function jatuhkanPeringatan(
        SilatMatch $match,
        Sudut $sudut,
        int $babak,
        TingkatPelanggaran $tingkat,
        ?string $catatan,
        User $pencatat,
    ): Penalty {
        $terpakai = $this->jumlahPeringatan($match, $sudut);
        $levelBaru = $terpakai + 1;
        $tingkatDiskualifikasi = config('scoring.tanding.hukuman.peringatan.tingkat_diskualifikasi');

        $penalty = Penalty::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'tier' => TingkatHukuman::Peringatan,
            'level' => $levelBaru,
            'points' => config("scoring.tanding.hukuman.peringatan.pengurangan.{$levelBaru}"),
            'violation_level' => $tingkat,
            'note' => $catatan,
            'created_by' => $pencatat->id,
        ]);

        if ($levelBaru >= $tingkatDiskualifikasi) {
            $lawan = $sudut === Sudut::Merah ? $match->blue : $match->red;
            $this->timer->akhiriPartai($match, $lawan, 'diskualifikasi');
        }

        return $penalty;
    }

    /**
     * Teguran langsung tanpa lewat klasifikasi tingkat pelanggaran --
     * dipakai HitunganTeknik untuk hitungan yang mencapai 9 (Pasal
     * 11.6.g.2). Bukan pelanggaran dalam arti ringan/sedang/berat, tapi
     * outputnya (tahap Teguran, aturan eskalasi babak) identik dengan
     * pelanggaran sedang, jadi disimpan dengan penanda yang sama -- sebab
     * sesungguhnya tetap terbaca dari `note`.
     */
    public function catatLangsungTeguran(SilatMatch $match, Sudut $sudut, int $babak, ?string $catatan, User $pencatat): Penalty
    {
        if ($this->sudahDiskualifikasi($match, $sudut)) {
            throw new RuntimeException('Pesilat ini sudah didiskualifikasi.');
        }

        return DB::transaction(
            fn () => $this->jatuhkanTeguran($match, $sudut, $babak, TingkatPelanggaran::Sedang, $catatan, $pencatat),
        );
    }

    /**
     * Pembinaan yang masih berlaku sejak eskalasi terakhir -- direset begitu
     * sebuah Teguran atau Peringatan tercatat untuk sudut ini.
     */
    public function jumlahPembinaan(SilatMatch $match, Sudut $sudut): int
    {
        $eskalasiTerakhir = $match->penalties()->berlaku()
            ->where('corner', $sudut)
            ->whereIn('tier', [TingkatHukuman::Teguran, TingkatHukuman::Peringatan])
            ->latest('id')
            ->first();

        return $match->penalties()->berlaku()
            ->where('corner', $sudut)
            ->where('tier', TingkatHukuman::Pembinaan)
            ->when($eskalasiTerakhir, fn ($q) => $q->where('id', '>', $eskalasiTerakhir->id))
            ->count();
    }

    /** Teguran yang tercatat pada babak ini -- tidak pernah lebih dari dua, sisanya jadi Peringatan. */
    public function jumlahTeguran(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        return $match->penalties()->berlaku()
            ->where('corner', $sudut)
            ->where('round', $babak)
            ->where('tier', TingkatHukuman::Teguran)
            ->count();
    }

    /** Peringatan sepanjang partai -- tidak pernah mereset antar babak. */
    public function jumlahPeringatan(SilatMatch $match, Sudut $sudut): int
    {
        return $match->penalties()->berlaku()
            ->where('corner', $sudut)
            ->where('tier', TingkatHukuman::Peringatan)
            ->count();
    }

    public function sudahDiskualifikasi(SilatMatch $match, Sudut $sudut): bool
    {
        return $this->jumlahPeringatan($match, $sudut)
            >= config('scoring.tanding.hukuman.peringatan.tingkat_diskualifikasi');
    }
}
