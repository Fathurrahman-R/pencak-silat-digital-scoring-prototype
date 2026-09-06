<?php

namespace App\Support\Rekap;

use App\Models\Bracket;
use App\Models\JurusEvent;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Bagan\TahapBaganJurus;
use App\Support\Jurus\JurusScoreCalculator;
use Closure;
use Illuminate\Support\Collection;

/**
 * Rekap medali -- FR-J-01, FR-J-02.
 *
 * Kategori Tanding: emas dan perak dari partai final, perunggu dari KEDUA
 * partai semifinal (naskah pencak silat tidak mengenal playoff perebutan
 * juara tiga -- pecundang kedua semifinalis sama-sama pulang dengan
 * perunggu). Bagan berukuran 2 (langsung final) tidak punya semifinal,
 * jadi tidak ada perunggu untuk kelas itu -- bukan bug, memang begitu
 * aturannya.
 *
 * Kategori Jurus: tiga peringkat teratas dari `JurusScoreCalculator::peringkat()`,
 * hanya dari penampilan yang sudah disahkan (`ratified_at` terisi) --
 * skor yang belum disahkan masih bisa dikoreksi dewan juri, jadi belum
 * pantas dianggap final untuk rekap.
 */
class RekapMedali
{
    /**
     * Hasil tanding dan jurus yang sudah tersusun, per kejuaraan.
     *
     * RekapController memanggil peringkatUmum(), tanding(), dan jurus() untuk
     * satu halaman. peringkatUmum() memanggil dua yang lain di dalamnya, jadi
     * keduanya berjalan DUA KALI per permintaan -- dan masing-masing memuat
     * seluruh partai kejuaraan beserta atlet dan kontingennya. Halaman rekap
     * dan ekspor PDF sama-sama menempuhnya.
     *
     * Dikunci per id kejuaraan, bukan satu properti tunggal: satu instance
     * bisa saja ditanyai dua kejuaraan berbeda dalam satu permintaan, dan
     * menjawab yang kedua dengan hasil yang pertama jauh lebih buruk daripada
     * menghitung dua kali.
     *
     * @var array<string, Collection<int, mixed>>
     */
    private array $ingatan = [];

    public function __construct(private readonly JurusScoreCalculator $jurusKalkulator) {}

    /** @param  Closure(): Collection<int, mixed>  $susun */
    private function ingat(string $jenis, Tournament $tournament, Closure $susun): Collection
    {
        return $this->ingatan["{$jenis}:{$tournament->id}"] ??= $susun();
    }

    /** @return Collection<int, array{kelas: WeightClass, emas: ?Registration, perak: ?Registration, perunggu: Collection<int, Registration>}> */
    public function tanding(Tournament $tournament): Collection
    {
        return $this->ingat('tanding', $tournament, fn () => $tournament->weightClasses()
            ->with(['bracket.matches.red.athletes', 'bracket.matches.red.contingent', 'bracket.matches.blue.athletes', 'bracket.matches.blue.contingent'])
            ->urutGolonganUsia()->orderBy('jenis_kelamin')->orderBy('code')
            ->get()
            ->map(fn (WeightClass $kelas) => $this->tandingSatuKelas($kelas))
            ->filter()
            ->values());
    }

    /** @return array{kelas: WeightClass, emas: ?Registration, perak: ?Registration, perunggu: Collection<int, Registration>}|null */
    private function tandingSatuKelas(WeightClass $kelas): ?array
    {
        $bracket = $kelas->bracket;

        if ($bracket === null || $bracket->matches->isEmpty()) {
            return null;
        }

        $babakFinal = $bracket->matches->max('round');
        $final = $bracket->matches->firstWhere('round', $babakFinal);

        if ($final === null || ! $final->disahkan() || $final->winner_registration_id === null) {
            return null;
        }

        $emas = $final->winner_registration_id === $final->red_registration_id ? $final->red : $final->blue;
        $perak = $final->winner_registration_id === $final->red_registration_id ? $final->blue : $final->red;

        $perunggu = $bracket->matches->where('round', $babakFinal - 1)
            ->map(function ($partai) {
                if ($partai->winner_registration_id === null) {
                    return null;
                }

                return $partai->winner_registration_id === $partai->red_registration_id ? $partai->blue : $partai->red;
            })
            ->filter()
            ->values();

        return ['kelas' => $kelas, 'emas' => $emas, 'perak' => $perak, 'perunggu' => $perunggu];
    }

    /** @return Collection<int, array{nomor: JurusEvent, emas: ?Registration, perak: ?Registration, perunggu: ?Registration}> */
    public function jurus(Tournament $tournament): Collection
    {
        return $this->ingat('jurus', $tournament, fn () => $tournament->jurusEvents()->aktif()
            ->with(['performances' => fn ($q) => $q->whereNotNull('ratified_at')->with('registration.athletes', 'registration.contingent', 'scores', 'deductions')])
            ->urutGolonganUsia()->orderBy('sort_order')
            ->get()
            ->map(fn (JurusEvent $nomor) => $this->jurusSatuNomor($nomor))
            ->filter()
            ->values());
    }

    /** @return array{nomor: JurusEvent, emas: ?Registration, perak: ?Registration, perunggu: ?Registration}|null */
    private function jurusSatuNomor(JurusEvent $nomor): ?array
    {
        /*
         * Naskah Pasal 12.1.b.6, berulang di tiap nomor: "minimal diikuti 3
         * peserta, bila hanya terdapat 2 peserta tetap dipertandingkan tetapi
         * perolehan medali tidak dihitung."
         *
         * Ditegakkan DI SINI, bukan saat bagan disusun. Pertandingannya tetap
         * sah dan hasilnya tetap tercatat -- yang gugur hanya medalinya.
         */
        $pesertaSah = $nomor->format->pakaiBagan()
            ? $this->pesertaBagan($nomor)
            : $nomor->performances->pluck('registration_id')->unique()->count();

        if ($pesertaSah < 3) {
            return null;
        }

        if ($nomor->format->pakaiBagan()) {
            return $this->jurusBattle($nomor);
        }

        if ($nomor->performances->isEmpty()) {
            return null;
        }

        $peringkat = $this->jurusKalkulator->peringkat($nomor->performances);

        return [
            'nomor' => $nomor,
            'emas' => $peringkat->get(0)?->registration,
            'perak' => $peringkat->get(1)?->registration,
            'perunggu' => $peringkat->get(2)?->registration,
        ];
    }

    /** Jumlah peserta yang masuk bagan nomor battle. */
    private function pesertaBagan(JurusEvent $nomor): int
    {
        return (int) ($nomor->bagan?->slots()->whereNotNull('registration_id')->count() ?? 0);
    }

    /**
     * Medali nomor battle dibaca dari bagannya, sama seperti Tanding.
     *
     * Perunggu HANYA satu, tidak seperti Tanding yang memberikannya kepada
     * kedua yang kalah di semifinal: naskah Pasal 12.1.f menyusun peringkat
     * Jurus sampai juara 3, dan bagan Jurus tidak mengenal perebutan tempat
     * ketiga yang dihapus di Tanding.
     *
     * Yang dipakai di sini: pemenang final emas, yang kalah perak, dan di
     * antara dua yang kalah semifinal dipilih yang skor akhirnya lebih baik --
     * lewat peringkat() yang sama, bukan urutan yang disalin.
     *
     * @return array{nomor: JurusEvent, emas: ?Registration, perak: ?Registration, perunggu: ?Registration}|null
     */
    private function jurusBattle(JurusEvent $nomor): ?array
    {
        $bagan = $nomor->bagan;

        if ($bagan === null) {
            return null;
        }

        $battles = $bagan->battles()->with('red', 'blue')->get();
        $rondeFinal = $battles->max('round');
        $final = $battles->firstWhere('round', $rondeFinal);

        if ($final === null || $final->winner_registration_id === null) {
            return null;
        }

        $menang = fn ($battle) => $battle->winner_registration_id === $battle->red_registration_id
            ? $battle->red
            : $battle->blue;
        $kalah = fn ($battle) => $battle->winner_registration_id === $battle->red_registration_id
            ? $battle->blue
            : $battle->red;

        $kalahSemifinal = $battles->where('round', $rondeFinal - 1)
            ->filter(fn ($b) => $b->winner_registration_id !== null)
            ->map($kalah)
            ->filter()
            ->values();

        return [
            'nomor' => $nomor,
            'emas' => $menang($final),
            'perak' => $kalah($final),
            'perunggu' => $this->perungguTerbaik($nomor, $kalahSemifinal),
        ];
    }

    /**
     * Satu perunggu dari dua yang kalah di semifinal.
     *
     * Dipilih lewat skor akhir penampilan semifinalnya, memakai peringkat()
     * yang sama dengan seluruh sistem -- bukan urutan bagan, yang tidak
     * menyatakan apa pun tentang siapa yang tampil lebih baik.
     *
     * @param  Collection<int, Registration>  $calon
     */
    private function perungguTerbaik(JurusEvent $nomor, Collection $calon): ?Registration
    {
        if ($calon->count() <= 1) {
            return $calon->first();
        }

        $penampilan = $nomor->performances
            ->whereIn('registration_id', $calon->pluck('id'))
            ->filter(fn ($p) => $p->tahap === TahapBaganJurus::SEMIFINAL);

        if ($penampilan->isEmpty()) {
            return $calon->first();
        }

        $terbaik = $this->jurusKalkulator->peringkat($penampilan)->first();

        return $calon->firstWhere('id', $terbaik?->registration_id) ?? $calon->first();
    }

    /**
     * Peringkat umum per kontingen -- diurutkan emas terbanyak, lalu perak,
     * lalu perunggu (konvensi lazim papan medali, bukan diatur naskah).
     *
     * @return Collection<int, array{kontingen: string, emas: int, perak: int, perunggu: int}>
     */
    public function peringkatUmum(Tournament $tournament): Collection
    {
        $hitung = [];

        $tambah = function (?Registration $r, string $jenis) use (&$hitung) {
            if ($r === null) {
                return;
            }

            $nama = $r->contingent->name;
            $hitung[$nama] ??= ['kontingen' => $nama, 'emas' => 0, 'perak' => 0, 'perunggu' => 0];
            $hitung[$nama][$jenis]++;
        };

        foreach ($this->tanding($tournament) as $baris) {
            $tambah($baris['emas'], 'emas');
            $tambah($baris['perak'], 'perak');
            foreach ($baris['perunggu'] as $r) {
                $tambah($r, 'perunggu');
            }
        }

        foreach ($this->jurus($tournament) as $baris) {
            $tambah($baris['emas'], 'emas');
            $tambah($baris['perak'], 'perak');
            $tambah($baris['perunggu'], 'perunggu');
        }

        return collect($hitung)->values()
            ->sortByDesc(fn ($b) => $b['emas'] * 1_000_000 + $b['perak'] * 1_000 + $b['perunggu'])
            ->values();
    }
}
