<?php

namespace App\Support\Jurus;

use App\Models\JurusBattle;
use App\Models\JurusPerformance;
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
 * Seri dipecah dengan urutan yang sama persis dengan peringkat: hukuman
 * terendah, lalu waktu terdekat ke acuan, lalu standar deviasi terendah, lalu
 * undian. Memakai `peringkat()` yang sudah ada, bukan menyalin urutannya --
 * urutan yang disalin adalah urutan yang suatu saat berbeda dari yang dipakai
 * rekap medali.
 */
class PutuskanBattle
{
    public function __construct(
        private readonly JurusScoreCalculator $kalkulator,
        private readonly PromosiPemenang $promosi,
    ) {}

    /**
     * @param  array<int, int>  $waktuAcuanMsPerId  waktu acuan per penampilan,
     *                                              dipakai pemecah seri kedua
     *
     * @throws RuntimeException
     */
    public function __invoke(JurusBattle $battle, array $waktuAcuanMsPerId = []): JurusBattle
    {
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

        $urut = $this->kalkulator->peringkat($penampilan, $waktuAcuanMsPerId);
        $unggul = $urut->first();

        return DB::transaction(function () use ($battle, $unggul, $urut) {
            $battle->update([
                'winner_registration_id' => $unggul->registration_id,
                /*
                 * Sebabnya selalu `angka` kecuali salah satu didiskualifikasi.
                 * Diskualifikasi ditetapkan Pengawas di panel penampilan, dan
                 * skornya sudah jadi 0.00 di sana -- yang tersisa di sini hanya
                 * menyebut sebabnya dengan benar di berita acara.
                 */
                'win_reason' => $urut->last()->didiskualifikasi ? 'diskualifikasi' : 'angka',
                'status' => JurusBattle::STATUS_SELESAI,
            ]);

            ($this->promosi)($battle->refresh());

            return $battle->refresh();
        });
    }
}
