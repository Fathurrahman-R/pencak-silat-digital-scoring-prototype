<?php

namespace App\Support\Scoring;

use App\Enums\GolonganUsia;
use App\Enums\Sudut;
use App\Enums\TingkatHukuman;
use App\Enums\TingkatPelanggaran;
use App\Models\Penalty;
use App\Models\SilatMatch;
use App\Models\TournamentRuleSetting;
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
 * Angkanya diambil dari SETELAN KEJUARAAN (`tournament_rule_settings`), bukan
 * dari `config/scoring.php`. Berkas config cuma nilai bawaan saat kejuaraan
 * dibuat; sesudah itu tiap kejuaraan memegang salinannya sendiri, dan panitia
 * mengubahnya lewat menu Peraturan tanpa menyentuh kode di lima laptop.
 * Termasuk `cakupan` tiap tahap -- 'babak' atau 'partai' -- yang menentukan
 * apakah hitungannya kembali nol tiap babak.
 *
 * Cakupan bawaannya: Pembinaan dan Teguran per BABAK -- hitungannya kembali nol
 * tiap babak baru, jadi tiap babak selalu dimulai dari tingkat I -- sementara
 * Peringatan berlaku sepanjang PARTAI dan tidak pernah mereset. Setelah dua
 * pembinaan dalam satu babak, pelanggaran ringan berikutnya naik jadi Teguran;
 * setelah dua teguran (Pasal 11.6.d.4.b.3), pelanggaran berikutnya naik jadi
 * Peringatan I. Ketiga cakupan itu bisa digeser panitia lewat menu Peraturan.
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
        return DB::transaction(function () use ($match, $sudut, $babak, $tingkat, $catatan, $pencatat) {
            $this->kunciPartai($match);

            /*
             * Diperiksa DI DALAM kunci.
             *
             * Di luar kunci, dua pencatatan yang tiba bersamaan sama-sama
             * melihat pesilat yang belum didiskualifikasi: yang pertama
             * menjatuhkan Peringatan ketiga dan mengakhiri partai, yang kedua
             * tetap lanjut dan menulis Peringatan keempat pada pesilat yang
             * sudah gugur.
             */
            if ($this->sudahDiskualifikasi($match, $sudut)) {
                throw new RuntimeException('Pesilat ini sudah didiskualifikasi.');
            }

            return match ($tingkat) {
                TingkatPelanggaran::Ringan => $this->tanganiRingan($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
                TingkatPelanggaran::Sedang => $this->jatuhkanTeguran($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
                TingkatPelanggaran::Berat => $this->jatuhkanPeringatan($match, $sudut, $babak, $tingkat, $catatan, $pencatat),
            };
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
        $tahap = $this->tahap($match, 'pembinaan');
        $terpakai = $this->jumlahPembinaan($match, $sudut, $babak);

        if ($terpakai < (int) $tahap['ambang_naik_ke_teguran']) {
            return Penalty::create([
                'match_id' => $match->id,
                'round' => $babak,
                'corner' => $sudut,
                'tier' => TingkatHukuman::Pembinaan,
                'level' => $terpakai + 1,
                'points' => $tahap['pengurangan'],
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
        $tahap = $this->tahap($match, 'teguran');
        $hukuman = $this->hukumanBerlaku($match);

        $terpakai = $this->hitungTeguran($hukuman, $sudut, $this->babakDihitung($tahap, $babak));

        $levelBaru = $terpakai + 1;

        /*
         * Pemicu Peringatan I: pelanggaran berikutnya sesudah dua teguran
         * dalam cakupan yang berlaku. Pada cakupan 'babak' -- bawaannya --
         * teguran babak sebelumnya tidak ikut dihitung; pada 'partai' seluruh
         * teguran partai ini dihitung.
         */
        if ($terpakai >= (int) $tahap['naik_ke_peringatan_dalam_babak_pada']) {
            // Teguran yang memicu eskalasi tidak pernah tercatat sebagai teguran.
            return $this->jatuhkanPeringatan($match, $sudut, $babak, $tingkat, $catatan, $pencatat);
        }

        return Penalty::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'tier' => TingkatHukuman::Teguran,
            'level' => $levelBaru,
            'points' => $tahap['pengurangan'][$levelBaru] ?? null,
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
        $tahap = $this->tahap($match, 'peringatan');
        $terpakai = $this->jumlahPeringatan($match, $sudut, $babak);
        $levelBaru = $terpakai + 1;
        $tingkatDiskualifikasi = (int) $tahap['tingkat_diskualifikasi'];

        $penalty = Penalty::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'tier' => TingkatHukuman::Peringatan,
            'level' => $levelBaru,
            'points' => $tahap['pengurangan'][$levelBaru] ?? null,
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
        return DB::transaction(function () use ($match, $sudut, $babak, $catatan, $pencatat) {
            $this->kunciPartai($match);

            if ($this->sudahDiskualifikasi($match, $sudut)) {
                throw new RuntimeException('Pesilat ini sudah didiskualifikasi.');
            }

            return $this->jatuhkanTeguran($match, $sudut, $babak, TingkatPelanggaran::Sedang, $catatan, $pencatat);
        });
    }

    /**
     * Mengunci baris partai selama tangga hukuman dihitung.
     *
     * Tiap tahap dihitung ulang dari baris `penalties` yang sudah tercatat.
     * Tanpa kunci, dua pencatatan yang tiba nyaris bersamaan -- wasit di
     * tablet dan operator di panel mencatat kejadian yang sama -- membaca
     * hitungan tangga yang persis sama dan menulis tahap kembar: dua Teguran
     * pertama (masing-masing -1) alih-alih Teguran 1 lalu Teguran 2 (-2),
     * dan eskalasi ke diskualifikasi tertunda satu pelanggaran.
     *
     * Kuncinya di baris partai, bukan di baris hukuman, karena yang harus
     * berbaris adalah seluruh perhitungan tangga satu partai -- pola yang
     * sama dipakai ConsensusEvaluator dan PollingVerifikasi.
     */
    private function kunciPartai(SilatMatch $match): void
    {
        SilatMatch::whereKey($match->id)->lockForUpdate()->first();
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
     * Setelannya diteruskan pemanggil, tidak diambil ulang di sini: satu
     * tarikan state memanggil metode ini dua kali dan tiap panggilan yang
     * mencari setelannya sendiri berarti kueri tambahan pada jalur yang paling
     * sering dilewati.
     *
     * @param  Collection<int, Penalty>  $hukuman  seluruh hukuman BERLAKU partai ini
     * @return array{pembinaan: int, teguran: int, peringatan: int, diskualifikasi: bool}
     */
    public function ringkasan(
        Collection $hukuman,
        Sudut $sudut,
        int $babak,
        ?TournamentRuleSetting $setelan = null,
        ?GolonganUsia $golongan = null,
    ): array {
        $pembinaan = $setelan?->hukumanTahap('pembinaan', $golongan) ?? (array) config('scoring.tanding.hukuman.pembinaan');
        $teguran = $setelan?->hukumanTahap('teguran', $golongan) ?? (array) config('scoring.tanding.hukuman.teguran');
        $tahapPeringatan = $setelan?->hukumanTahap('peringatan', $golongan) ?? (array) config('scoring.tanding.hukuman.peringatan');

        $peringatan = $this->hitungPeringatan($hukuman, $sudut, $this->babakDihitung($tahapPeringatan, $babak));

        return [
            'pembinaan' => $this->hitungPembinaan($hukuman, $sudut, $this->babakDihitung($pembinaan, $babak)),
            // Petak yang menyala di panel harus padam persis saat penegakannya
            // mereset, jadi keduanya membaca cakupan yang sama.
            'teguran' => $this->hitungTeguran($hukuman, $sudut, $this->babakDihitung($teguran, $babak)),
            'peringatan' => $peringatan,
            'diskualifikasi' => $peringatan >= (int) $tahapPeringatan['tingkat_diskualifikasi'],
        ];
    }

    /**
     * Babak yang dipakai menyaring, menurut cakupan tahapnya.
     *
     * `null` berarti "jangan disaring" -- yaitu cakupan sepanjang partai.
     *
     * @param  array<string, mixed>  $tahap
     */
    private function babakDihitung(array $tahap, int $babak): ?int
    {
        return ($tahap['cakupan'] ?? 'babak') === 'partai' ? null : $babak;
    }

    /**
     * Tahap tangga hukuman yang BERLAKU untuk partai ini.
     *
     * Golongan usia kelasnya ikut diteruskan, karena setelan kejuaraan boleh
     * dikecualikan per golongan: cakupan teguran yang mereset tiap babak untuk
     * Usia Dini bisa berjalan sepanjang partai untuk Dewasa di kejuaraan yang
     * sama.
     *
     * @return array<string, mixed>
     */
    private function tahap(SilatMatch $match, string $tahap): array
    {
        $kelas = $match->bracket->weightClass;

        return $kelas->tournament->peraturan()->hukumanTahap($tahap, $kelas->golongan_usia);
    }

    /** Pembinaan sudut ini dalam cakupan yang berlaku. */
    public function jumlahPembinaan(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        return $this->hitungPembinaan(
            $this->hukumanBerlaku($match),
            $sudut,
            $this->babakDihitung($this->tahap($match, 'pembinaan'), $babak),
        );
    }

    /**
     * Teguran sudut ini. Dengan `$babak` berarti dalam cakupan yang berlaku --
     * itulah tingkatnya (Teguran I, II). Tanpa `$babak` berarti seluruh teguran
     * partai, hanya untuk rekap.
     */
    public function jumlahTeguran(SilatMatch $match, Sudut $sudut, ?int $babak = null): int
    {
        return $this->hitungTeguran(
            $this->hukumanBerlaku($match),
            $sudut,
            $babak === null ? null : $this->babakDihitung($this->tahap($match, 'teguran'), $babak),
        );
    }

    /** Peringatan sudut ini dalam cakupan yang berlaku -- bawaannya sepanjang partai. */
    public function jumlahPeringatan(SilatMatch $match, Sudut $sudut, ?int $babak = null): int
    {
        return $this->hitungPeringatan(
            $this->hukumanBerlaku($match),
            $sudut,
            $babak === null ? null : $this->babakDihitung($this->tahap($match, 'peringatan'), $babak),
        );
    }

    public function sudahDiskualifikasi(SilatMatch $match, Sudut $sudut): bool
    {
        /*
         * Selalu sepanjang partai, apa pun cakupan Peringatan.
         *
         * Diskualifikasi mengeluarkan pesilat dari pertandingan; ia tidak bisa
         * kembali nol di babak berikutnya. Cakupan yang disetel panitia
         * mengatur kapan tingkat Peringatan naik, bukan apakah pesilat yang
         * sudah gugur boleh bertanding lagi.
         */
        return $this->jumlahPeringatan($match, $sudut)
            >= (int) $this->tahap($match, 'peringatan')['tingkat_diskualifikasi'];
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

    /**
     * @param  Collection<int, Penalty>  $hukuman
     * @param  int|null  $babak  null berarti sepanjang partai
     */
    private function hitungPembinaan(Collection $hukuman, Sudut $sudut, ?int $babak): int
    {
        return $hukuman
            ->where('corner', $sudut)
            ->when($babak !== null, fn (Collection $c) => $c->where('round', $babak))
            ->where('tier', TingkatHukuman::Pembinaan)
            ->count();
    }

    /**
     * @param  Collection<int, Penalty>  $hukuman
     * @param  int|null  $babak  null berarti sepanjang partai
     */
    private function hitungTeguran(Collection $hukuman, Sudut $sudut, ?int $babak = null): int
    {
        return $hukuman
            ->where('corner', $sudut)
            ->when($babak !== null, fn (Collection $c) => $c->where('round', $babak))
            ->where('tier', TingkatHukuman::Teguran)
            ->count();
    }

    /**
     * @param  Collection<int, Penalty>  $hukuman
     * @param  int|null  $babak  null berarti sepanjang partai -- bawaan Peringatan
     */
    private function hitungPeringatan(Collection $hukuman, Sudut $sudut, ?int $babak = null): int
    {
        return $hukuman
            ->where('corner', $sudut)
            ->when($babak !== null, fn (Collection $c) => $c->where('round', $babak))
            ->where('tier', TingkatHukuman::Peringatan)
            ->count();
    }
}
