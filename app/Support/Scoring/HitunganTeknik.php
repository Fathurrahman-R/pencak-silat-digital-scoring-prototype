<?php

namespace App\Support\Scoring;

use App\Enums\Sudut;
use App\Models\SilatMatch;
use App\Models\TechnicalCount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hitungan wasit terhadap pesilat yang jatuh -- Pasal 11.6.g.2 dan 11.6.g.3.
 *
 * Tiga akibat yang bisa terjadi dari satu hitungan, dan ketiganya bisa
 * bertumpuk pada hitungan yang sama: hitungan ke-9 menjatuhkan Teguran I,
 * hitungan ke-10 langsung mengakhiri partai (menang mutlak), dan hitungan
 * ketiga berturut-turut terhadap sudut yang sama dalam satu babak membuat
 * lawannya menang teknik.
 */
class HitunganTeknik
{
    public function __construct(
        private readonly TanggaHukuman $tangga,
        private readonly MatchTimer $timer,
    ) {}

    /**
     * Hitungan teknik untuk KEDUA sudut sekaligus -- Pasal 11.6.e.2.c.(b).
     *
     * "Jika kedua Pesilat tidak segera bangkit, maka dilakukan hitungan teknik
     * untuk keduanya." Satu tindakan wasit, dua baris tercatat: riwayatnya
     * tetap per sudut seperti hitungan biasa, karena berita acara menyebut
     * hitungan yang diterima masing-masing pesilat.
     *
     * TIDAK mengakhiri partai sendiri meski mencapai sepuluh. Naskah menyuruh
     * mempertimbangkan beberapa faktor -- berat badan atau nilai terbanyak --
     * dan keduanya butuh keputusan manusia. Yang dilakukan di sini hanya
     * mencatat; tawarannya dibaca panel lewat
     * TandingScoreCalculator::penyelesaianHitunganSerentak().
     *
     * @return array<int, TechnicalCount>
     */
    public function catatSerentak(SilatMatch $match, int $babak, int $hitunganTertinggi, User $pencatat): array
    {
        if ($hitunganTertinggi < 1 || $hitunganTertinggi > 10) {
            throw new RuntimeException('Hitungan harus antara 1 dan 10.');
        }

        return DB::transaction(fn () => collect(Sudut::cases())
            ->map(fn (Sudut $sudut) => TechnicalCount::create([
                'match_id' => $match->id,
                'round' => $babak,
                'corner' => $sudut,
                'count_reached' => $hitunganTertinggi,
                'created_by' => $pencatat->id,
            ]))
            ->all());
    }

    public function catat(SilatMatch $match, Sudut $sudut, int $babak, int $hitunganTertinggi, User $pencatat): TechnicalCount
    {
        if ($hitunganTertinggi < 1 || $hitunganTertinggi > 10) {
            throw new RuntimeException('Hitungan harus antara 1 dan 10.');
        }

        $hitungan = TechnicalCount::create([
            'match_id' => $match->id,
            'round' => $babak,
            'corner' => $sudut,
            'count_reached' => $hitunganTertinggi,
            'created_by' => $pencatat->id,
        ]);

        $ambangTeguran = config('scoring.tanding.hitungan_teknik.teguran_pada_hitungan');
        $ambangMutlak = config('scoring.tanding.hitungan_teknik.mutlak_pada_hitungan');
        $beruntunMenang = config('scoring.tanding.hitungan_teknik.menang_teknik_setelah_hitungan_beruntun');

        if ($hitunganTertinggi >= $ambangTeguran && ! $this->tangga->sudahDiskualifikasi($match, $sudut)) {
            $this->tangga->catatLangsungTeguran(
                $match, $sudut, $babak, "Hitungan teknik mencapai {$hitunganTertinggi}.", $pencatat,
            );
        }

        if ($hitunganTertinggi >= $ambangMutlak) {
            $this->timer->akhiriPartai($match, $this->lawan($match, $sudut), 'mutlak');

            return $hitungan->refresh();
        }

        if ($this->beruntun($match, $sudut, $babak) >= $beruntunMenang) {
            $this->timer->akhiriPartai($match, $this->lawan($match, $sudut), 'teknik');
        }

        return $hitungan->refresh();
    }

    /**
     * Berapa kali beruntun sudut ini dihitung dalam babak ini, tanpa
     * diselingi hitungan terhadap sudut lawan.
     *
     * Terbuka untuk dibaca panel, bukan cuma dipakai di dalam sini: wasit yang
     * tidak melihat angka ini menekan hitungan ketiga tanpa tahu bahwa
     * tekanannya mengakhiri partai.
     */
    public function beruntun(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        return $this->hitungBeruntun($this->hitunganBabak($match, $babak), $sudut);
    }

    /**
     * Ketiga angka hitungan teknik satu sudut, dari baris yang SUDAH dimuat
     * pemanggilnya.
     *
     * Sama alasannya dengan TanggaHukuman::ringkasan(): endpoint state butuh
     * ketiganya untuk kedua sudut, dan enam perjalanan ke basis data untuk
     * angka sekecil ini mengantre tepat di depan tekanan tombol berikutnya.
     *
     * @param  Collection<int, TechnicalCount>  $hitungan  hitungan babak ini, terbaru dulu
     * @return array{jumlah: int, beruntun: int, terakhir: int|null}
     */
    public function ringkasan(Collection $hitungan, Sudut $sudut): array
    {
        $sudutIni = $hitungan->where('corner', $sudut);

        return [
            'jumlah' => $sudutIni->count(),
            'beruntun' => $this->hitungBeruntun($hitungan, $sudut),
            'terakhir' => $sudutIni->first()?->count_reached,
        ];
    }

    /**
     * Hitungan satu babak, terbaru dulu -- urutan yang dipakai ringkasan()
     * maupun jalur tulis.
     *
     * @return Collection<int, TechnicalCount>
     */
    public function hitunganBabak(SilatMatch $match, int $babak): Collection
    {
        return $match->technicalCounts()->where('round', $babak)->orderByDesc('id')->get();
    }

    /** @param  Collection<int, TechnicalCount>  $hitungan  terbaru dulu */
    private function hitungBeruntun(Collection $hitungan, Sudut $sudut): int
    {
        $beruntun = 0;

        foreach ($hitungan as $h) {
            if ($h->corner !== $sudut) {
                break;
            }

            $beruntun++;
        }

        return $beruntun;
    }

    /**
     * Hitungan tertinggi yang terakhir dicatat untuk sudut ini di babak ini.
     *
     * Kosong berarti sudut ini belum pernah dihitung babak ini. Dipakai panel
     * wasit untuk menyatakan sampai berapa hitungan terakhir berjalan --
     * angka yang menentukan Teguran I (hitungan 9) dan menang mutlak
     * (hitungan 10), dan yang selama ini hanya ada di kepala wasit.
     */
    public function terakhir(SilatMatch $match, Sudut $sudut, int $babak): ?int
    {
        return $match->technicalCounts()
            ->where('round', $babak)
            ->where('corner', $sudut)
            ->orderByDesc('id')
            ->value('count_reached');
    }

    /** Berapa kali sudut ini dihitung sepanjang babak ini, beruntun maupun tidak. */
    public function jumlah(SilatMatch $match, Sudut $sudut, int $babak): int
    {
        return $match->technicalCounts()
            ->where('round', $babak)
            ->where('corner', $sudut)
            ->count();
    }

    private function lawan(SilatMatch $match, Sudut $sudut)
    {
        return $sudut === Sudut::Merah ? $match->blue : $match->red;
    }
}
