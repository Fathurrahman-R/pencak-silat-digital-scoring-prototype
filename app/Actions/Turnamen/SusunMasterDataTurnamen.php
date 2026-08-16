<?php

namespace App\Actions\Turnamen;

use App\Enums\GolonganUsia;
use App\Enums\JenisKelamin;
use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use App\Support\Peraturan\KelasTandingNaskah;
use App\Support\Peraturan\NomorJurusNaskah;
use Illuminate\Support\Facades\DB;

/**
 * Menuangkan isi naskah 2025 ke satu kejuaraan yang baru dibuat.
 *
 * Panitia lazim hanya mempertandingkan sebagian golongan usia, jadi kejuaraan
 * dimulai dengan tabel penuh dan panitia mematikan yang tidak dipakai —
 * bukan sebaliknya. Mengetik ulang dua ratus lebih baris kelas berat adalah
 * pekerjaan yang salahnya baru ketahuan saat seorang atlet ditolak timbang
 * badan di hari-H.
 *
 * Aman dipanggil ulang: baris yang sudah ada diperbarui, bukan digandakan.
 * Kolom `is_active` sengaja tidak ikut ditulis ulang supaya kelas yang sudah
 * dimatikan panitia tidak diam-diam hidup lagi.
 */
class SusunMasterDataTurnamen
{
    /** @param  list<GolonganUsia>|null  $golongan  Semua golongan bila kosong. */
    public function __invoke(Tournament $tournament, ?array $golongan = null): void
    {
        $golongan ??= GolonganUsia::cases();

        DB::transaction(function () use ($tournament, $golongan) {
            $this->setelanPeraturan($tournament);
            $this->kelasTanding($tournament, $golongan);
            $this->nomorJurus($tournament, $golongan);
        });
    }

    private function setelanPeraturan(Tournament $tournament): void
    {
        // firstOrCreate, bukan updateOrCreate: setelan yang sudah disunting
        // panitia tidak boleh ditarik kembali ke bawaan naskah.
        $tournament->ruleSetting()->firstOrCreate([], TournamentRuleSetting::bawaan());
    }

    /** @param  list<GolonganUsia>  $golongan */
    private function kelasTanding(Tournament $tournament, array $golongan): void
    {
        foreach ($golongan as $usia) {
            if (! $usia->adaTanding() || ! $usia->pakaiKelasBerat()) {
                continue;
            }

            foreach (JenisKelamin::cases() as $jenisKelamin) {
                $urutan = 0;

                foreach (KelasTandingNaskah::untuk($usia, $jenisKelamin) as $kelas) {
                    $tournament->weightClasses()->updateOrCreate(
                        [
                            'golongan_usia' => $usia,
                            'jenis_kelamin' => $jenisKelamin,
                            'code' => $kelas['code'],
                        ],
                        [...$kelas, 'sort_order' => $urutan++],
                    );
                }
            }
        }
    }

    /** @param  list<GolonganUsia>  $golongan */
    private function nomorJurus(Tournament $tournament, array $golongan): void
    {
        foreach ($golongan as $usia) {
            foreach (JenisKelamin::cases() as $jenisKelamin) {
                $urutan = 0;

                foreach (NomorJurusNaskah::untuk($usia) as $jenis) {
                    $tournament->jurusEvents()->updateOrCreate(
                        [
                            'jenis' => $jenis,
                            'golongan_usia' => $usia,
                            'jenis_kelamin' => $jenisKelamin,
                        ],
                        [
                            'waktu_acuan_ms' => NomorJurusNaskah::waktuAcuanMs($jenis),
                            'sort_order' => $urutan++,
                        ],
                    );
                }
            }
        }
    }
}
