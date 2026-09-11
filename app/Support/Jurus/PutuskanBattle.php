<?php

namespace App\Support\Jurus;

use App\Models\JurusBattle;
use App\Models\JurusPerformance;
use App\Models\User;
use App\Support\Bagan\PromosiPemenang;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menetapkan pemenang satu battle Jurus, lalu menaikkannya ke ronde berikutnya.
 *
 * Yang unggul skor akhirnya menang -- naskah Pasal 12.1.f. Skor akhir tetap
 * median seluruh juri dikurangi pengurangan, dihitung JurusScoreCalculator yang
 * sudah ada; kelas ini tidak menghitung apa pun sendiri.
 *
 * # Seri TIDAK dipecah sistem
 *
 * Sebelumnya seri diserahkan ke `JurusScoreCalculator::peringkat()`, yang
 * memecahnya berjenjang: hukuman terendah, waktu terdekat ke acuan, standar
 * deviasi terendah, lalu UNDIAN. Rantai itu aturan peringkat nomor berformat
 * penampilan, dan ujungnya undian -- artinya seorang pesilat bisa tersingkir
 * dari bagan gugur oleh angka acak yang tidak pernah diumumkan kepada siapa
 * pun, dan yang tidak bisa dijelaskan kepada pelatih yang menanyakannya.
 *
 * Di battle, skor akhir yang SAMA berhenti di sini dan menunggu Ketua
 * Pertandingan. Alasannya wajib, dan ikut tercetak di berita acara.
 *
 * `peringkat()` tetap dipakai apa adanya untuk nomor berformat penampilan dan
 * rekap medali -- yang berubah hanya jalur battle.
 */
class PutuskanBattle
{
    public function __construct(
        private readonly JurusScoreCalculator $kalkulator,
        private readonly PromosiPemenang $promosi,
    ) {}

    /**
     * @param  array<int, int>  $waktuAcuanMsPerId  waktu acuan per penampilan;
     *                                              tersisa untuk pemanggil lama,
     *                                              tidak lagi dipakai memecah seri
     * @param  int|null  $pemenangRegistrationId  pilihan Ketua Pertandingan,
     *                                            hanya sah saat skornya seri
     * @param  string|null  $alasan  wajib menyertai pilihan Ketua
     *
     * @throws RuntimeException
     */
    public function __invoke(
        JurusBattle $battle,
        array $waktuAcuanMsPerId = [],
        ?int $pemenangRegistrationId = null,
        ?string $alasan = null,
        ?User $oleh = null,
    ): JurusBattle {
        if ($battle->selesai()) {
            throw new RuntimeException('Battle ini sudah selesai.');
        }

        $penampilan = $battle->performances()->with('scores', 'deductions')->get();

        if ($penampilan->count() < 2) {
            throw new RuntimeException(
                'Battle butuh dua penampilan sebelum pemenangnya bisa ditetapkan.',
            );
        }

        $belumDisahkan = $penampilan->firstWhere(fn (JurusPerformance $p) => $p->ratified_at === null);

        if ($belumDisahkan !== null) {
            throw new RuntimeException(
                'Kedua penampilan harus disahkan lebih dulu sebelum pemenang battle ditetapkan.',
            );
        }

        $skor = $penampilan->mapWithKeys(fn (JurusPerformance $p) => [
            $p->registration_id => round($this->kalkulator->skorAkhir($p), 2),
        ]);

        $tertinggi = $skor->max();
        $unggul = $skor->filter(fn (float $nilai) => $nilai === $tertinggi);

        $seri = $unggul->count() > 1;

        if (! $seri) {
            /*
             * Skor berbeda: pilihan manual DITOLAK, bukan diterima diam-diam.
             * Menerimanya berarti satu tekanan tombol bisa membalikkan hasil
             * yang sudah sah menurut angka, dan tidak ada satu pun jejak yang
             * membedakannya dari keputusan seri yang wajar.
             */
            if ($pemenangRegistrationId !== null && $pemenangRegistrationId !== $unggul->keys()->first()) {
                throw new RuntimeException(
                    'Skor akhir kedua sudut tidak sama — pemenangnya ditentukan angka, bukan dipilih.',
                );
            }

            return $this->tetapkan(
                $battle,
                (int) $unggul->keys()->first(),
                $penampilan->firstWhere('didiskualifikasi', true) !== null ? 'diskualifikasi' : 'angka',
            );
        }

        if ($pemenangRegistrationId === null || trim((string) $alasan) === '') {
            throw new RuntimeException(sprintf(
                'Skor akhir kedua sudut sama (%s). Ketua Pertandingan yang menetapkan pemenangnya, dan alasannya wajib ditulis.',
                number_format((float) $tertinggi, 2),
            ));
        }

        if (! $skor->keys()->contains($pemenangRegistrationId)) {
            throw new RuntimeException('Sudut yang dipilih bukan peserta battle ini.');
        }

        return $this->tetapkan($battle, $pemenangRegistrationId, 'keputusan_ketua', trim($alasan), $oleh);
    }

    private function tetapkan(
        JurusBattle $battle,
        int $pemenangRegistrationId,
        string $sebab,
        ?string $alasan = null,
        ?User $oleh = null,
    ): JurusBattle {
        return DB::transaction(function () use ($battle, $pemenangRegistrationId, $sebab, $alasan, $oleh) {
            $battle->update([
                'winner_registration_id' => $pemenangRegistrationId,
                /*
                 * Sebabnya `angka` kecuali salah satu didiskualifikasi, atau
                 * kecuali skornya seri dan Ketua yang memutus. Diskualifikasi
                 * ditetapkan Pengawas di panel penampilan, dan skornya sudah
                 * jadi 0.00 di sana -- yang tersisa di sini hanya menyebut
                 * sebabnya dengan benar di berita acara.
                 */
                'win_reason' => $sebab,
                'keputusan_alasan' => $alasan,
                'keputusan_oleh' => $oleh?->id,
                'status' => JurusBattle::STATUS_SELESAI,
            ]);

            ($this->promosi)($battle->refresh());

            return $battle->refresh();
        });
    }
}
