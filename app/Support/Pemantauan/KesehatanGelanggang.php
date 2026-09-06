<?php

namespace App\Support\Pemantauan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tiga angka yang memberi tahu operator kapan gelanggang perlu ditangani.
 *
 * # Kenapa tiga, bukan satu
 *
 * Yang dirasakan operator cuma satu hal: panelnya melambat. Tapi "melambat"
 * bukan sesuatu yang bisa ditindaklanjuti. Dua angka lainnya menjelaskan
 * kenapa, dan masing-masing menunjuk tindakan yang berbeda:
 *
 *   waktu tarikan state    gejalanya. Paling jujur, karena ia mengukur yang
 *                          benar-benar dialami, bukan tebakan penyebabnya.
 *
 *   jumlah judge_inputs    sebab yang bisa dipangkas, di jeda antar sesi.
 *
 *   partai belum terarsip  bukan soal kecepatan sama sekali. Ia menandakan
 *                          dorongan arsip sedang tidak bekerja, dan tiap
 *                          angkanya adalah satu partai yang buktinya cuma
 *                          ada di satu laptop.
 *
 * # Kenapa jumlah baris diperkirakan, bukan dihitung
 *
 * `count(*)` pada tabel dengan ratusan ribu baris di InnoDB berarti memindai
 * indeks seluruhnya. Alat ukur yang membebani jalur yang sedang diselidiki
 * akan ikut memperburuk angka yang sedang dibacanya. Perkiraan dari
 * information_schema cukup: yang ditanyakan bukan "berapa persisnya"
 * melainkan "sudah waktunya dipangkas atau belum".
 *
 * Perkiraan itu ikut basi kalau statistik tabelnya lama tidak diperbarui --
 * itu keterbatasan yang diterima, karena arah dan besarannya tetap benar.
 */
class KesehatanGelanggang
{
    public const HIJAU = 'hijau';

    public const KUNING = 'kuning';

    public const MERAH = 'merah';

    /**
     * @return array{
     *     tingkat: string,
     *     metrik: list<array{nama: string, nilai: int, tingkat: string, tindakan: ?string}>,
     * }
     */
    public function periksa(): array
    {
        return Cache::remember(
            'kesehatan-gelanggang',
            (int) config('pemantauan.cache_detik', 60),
            fn () => $this->hitung(),
        );
    }

    /** @return array{tingkat: string, metrik: list<array<string, mixed>>} */
    private function hitung(): array
    {
        $metrik = [
            $this->nilai(
                'Waktu tarikan panel (p95)',
                $this->stateP95(),
                (array) config('pemantauan.ambang.state_p95_ms'),
                kuning: 'Panel mulai melambat. Periksa dua angka di bawah.',
                merah: 'Panel melambat jelas. Pangkas riwayat juri di jeda berikutnya.',
                satuan: 'ms',
            ),
            $this->nilai(
                'Baris riwayat juri',
                $this->barisJudgeInputs(),
                (array) config('pemantauan.ambang.baris_judge_inputs'),
                kuning: 'Riwayat juri menumpuk. Jalankan Pangkas Riwayat Juri di jeda antar sesi.',
                merah: 'Riwayat juri sangat menumpuk. Pangkas sebelum sesi berikutnya dimulai.',
            ),
            $this->nilai(
                'Partai belum terarsip',
                $this->partaiBelumTerarsip(),
                (array) config('pemantauan.ambang.partai_belum_terarsip'),
                kuning: 'Ada partai yang buktinya belum sampai ke node arsip. Tekan Kirim Arsip.',
                merah: 'Dorongan arsip tidak bekerja. Periksa sambungan ke node global sekarang.',
            ),
        ];

        $tingkat = self::HIJAU;

        foreach ($metrik as $satu) {
            if ($satu['tingkat'] === self::MERAH) {
                $tingkat = self::MERAH;
                break;
            }

            if ($satu['tingkat'] === self::KUNING) {
                $tingkat = self::KUNING;
            }
        }

        return ['tingkat' => $tingkat, 'metrik' => $metrik];
    }

    /** @param  array{kuning: int, merah: int}  $ambang */
    private function nilai(
        string $nama,
        int $angka,
        array $ambang,
        string $kuning,
        string $merah,
        string $satuan = '',
    ): array {
        $tingkat = match (true) {
            $angka >= ($ambang['merah'] ?? PHP_INT_MAX) => self::MERAH,
            $angka >= ($ambang['kuning'] ?? PHP_INT_MAX) => self::KUNING,
            default => self::HIJAU,
        };

        return [
            'nama' => $nama,
            'nilai' => $angka,
            'satuan' => $satuan,
            'tingkat' => $tingkat,
            'ambang_kuning' => $ambang['kuning'] ?? null,
            // Tindakan disebutkan, bukan cuma angkanya. Lencana yang menampilkan
            // "640 ms" tanpa menyebut apa yang harus dilakukan menyerahkan
            // penafsirannya ke orang yang sedang mengurus pertandingan.
            'tindakan' => match ($tingkat) {
                self::MERAH => $merah,
                self::KUNING => $kuning,
                default => null,
            },
        ];
    }

    /** Persentil 95 dari sampel waktu tarikan state yang tercatat. */
    private function stateP95(): int
    {
        $berkas = (string) config('pemantauan.sampel.berkas');

        try {
            if (! Storage::disk('local')->exists($berkas)) {
                return 0;
            }

            $baris = array_filter(explode("\n", Storage::disk('local')->get($berkas)));
        } catch (Throwable) {
            return 0;
        }

        $angka = array_values(array_filter(array_map('intval', $baris), static fn ($n) => $n > 0));

        if ($angka === []) {
            return 0;
        }

        sort($angka);

        // Persentil 95, bukan rata-rata: yang membuat operator mengeluh bukan
        // tarikan yang biasa, melainkan yang sesekali menggantung.
        $posisi = (int) floor(count($angka) * 0.95);

        return $angka[min($posisi, count($angka) - 1)];
    }

    private function barisJudgeInputs(): int
    {
        try {
            return (int) (DB::selectOne(
                'SELECT TABLE_ROWS n FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['judge_inputs'],
            )?->n ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function partaiBelumTerarsip(): int
    {
        try {
            return (int) DB::table('arsip_keluar')
                ->where('status', '!=', 'diterima')
                ->count();
        } catch (Throwable) {
            // Tabelnya belum ada di pemasangan yang belum memakai arsip.
            return 0;
        }
    }
}
