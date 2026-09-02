<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Enums\TingkatPelanggaran;
use App\Models\Penalty;
use App\Models\SilatMatch;
use App\Models\User;
use Illuminate\Support\Collection;
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
     * Keempat angka tangga hukuman satu sudut, dihitung dari baris yang SUDAH
     * dimuat pemanggilnya.
     *
     * Endpoint state butuh keempatnya untuk kedua sudut sekaligus. Ditanyakan
     * satu per satu, itu sepuluh perjalanan ke basis data untuk satu tarikan
     * layar yang terjadi tiap kali ada nilai terbit -- dan tarikan itulah yang
     * mengantre di belakang tekanan tombol juri berikutnya. Satu partai tidak
     * pernah punya lebih dari belasan baris hukuman, jadi memuat semuanya
     * sekali selalu lebih murah daripada menghitungnya berkali-kali.
     *
     * @param  Collection<int, Penalty>  $hukuman  seluruh hukuman BERLAKU partai ini
     * @return array{pembinaan: int, teguran: int, peringatan: int, diskualifikasi: bool}
     */
    public function ringkasan(Collection $hukuman, Sudut $sudut, int $babak): array
    {
        $peringatan = $this->hitungPeringatan($hukuman, $sudut);

        return [
            'pembinaan' => $this->hitungPembinaan($hukuman, $sudut),
            'teguran' => $this->hitungTeguran($hukuman, $sudut, $babak),
            'peringatan' => $peringatan,
            'diskualifikasi' => $peringatan >= config('scoring.tanding.hukuman.peringatan.tingkat_diskualifikasi'),
        ];
    }

    /**
     * Pembinaan yang masih berlaku sejak eskalasi terakhir -- direset begitu
     * sebuah Teguran atau Peringatan tercatat untuk sudut ini.
     */
    public function jumlahPembinaan(SilatMatch $match, Sudut $sudut): int
    {
        return $this->hitungPembinaan($this->hukumanBerlaku($match), $sudut);
    }

    /** Teguran yang tercatat pada babak ini -- tidak pernah lebih dari dua, sisanya jadi Peringatan. */
    public function jumlahTeguran(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        return $this->hitungTeguran($this->hukumanBerlaku($match), $sudut, $babak);
    }

    /** Peringatan sepanjang partai -- tidak pernah mereset antar babak. */
    public function jumlahPeringatan(SilatMatch $match, Sudut $sudut): int
    {
        return $this->hitungPeringatan($this->hukumanBerlaku($match), $sudut);
    }

    public function sudahDiskualifikasi(SilatMatch $match, Sudut $sudut): bool
    {
        return $this->jumlahPeringatan($match, $sudut)
            >= config('scoring.tanding.hukuman.peringatan.tingkat_diskualifikasi');
    }

    /**
     * Selalu ditanyakan ulang, tidak pernah dari relasi yang sudah dimuat:
     * pemanggilnya jalur TULIS -- ia baru saja mencatat sebuah hukuman dan
     * sedang menentukan tahap berikutnya. Relasi yang dimuat sebelum
     * pencatatan itu akan menjawab dengan keadaan sebelum tekanan wasit.
     *
     * @return Collection<int, Penalty>
     */
    private function hukumanBerlaku(SilatMatch $match): Collection
    {
        return $match->penalties()->berlaku()->get(['id', 'round', 'corner', 'tier']);
    }

    /** @param  Collection<int, Penalty>  $hukuman */
    private function hitungPembinaan(Collection $hukuman, Sudut $sudut): int
    {
        $sudutIni = $hukuman->where('corner', $sudut);

        $eskalasiTerakhir = $sudutIni
            ->whereIn('tier', [TingkatHukuman::Teguran, TingkatHukuman::Peringatan])
            ->max('id');

        return $sudutIni
            ->where('tier', TingkatHukuman::Pembinaan)
            ->when($eskalasiTerakhir, fn (Collection $c) => $c->where('id', '>', $eskalasiTerakhir))
            ->count();
    }

    /** @param  Collection<int, Penalty>  $hukuman */
    private function hitungTeguran(Collection $hukuman, Sudut $sudut, int $babak): int
    {
        return $hukuman
            ->where('corner', $sudut)
            ->where('round', $babak)
            ->where('tier', TingkatHukuman::Teguran)
            ->count();
    }

    /** @param  Collection<int, Penalty>  $hukuman */
    private function hitungPeringatan(Collection $hukuman, Sudut $sudut): int
    {
        return $hukuman
            ->where('corner', $sudut)
            ->where('tier', TingkatHukuman::Peringatan)
            ->count();
    }
}
