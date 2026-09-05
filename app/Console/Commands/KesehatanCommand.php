<?php

namespace App\Console\Commands;

use App\Support\Pemantauan\KesehatanGelanggang;
use Illuminate\Console\Command;

/**
 * Ketiga metrik kesehatan gelanggang, dibaca dari baris perintah.
 *
 * Ada supaya pemeriksaan pra-hari-H bisa dilakukan tanpa membuka peramban dan
 * login -- panitia yang menyiapkan lima laptop pagi-pagi tidak perlu melakukan
 * itu lima kali.
 *
 * Exit code-nya ikut warna lencana: gagal saat merah. Itu yang membuatnya bisa
 * dipakai di skrip penyalaan server sebagai penjaga, bukan cuma sebagai
 * tampilan.
 */
class KesehatanCommand extends Command
{
    protected $signature = 'silat:kesehatan';

    protected $description = 'Memeriksa kesehatan gelanggang: waktu tarikan panel, tumpukan riwayat juri, dan arsip yang belum terkirim';

    public function handle(KesehatanGelanggang $kesehatan): int
    {
        $hasil = $kesehatan->periksa();

        foreach ($hasil['metrik'] as $metrik) {
            $angka = number_format($metrik['nilai']).$metrik['satuan'];

            $this->components->twoColumnDetail(
                $metrik['nama'],
                match ($metrik['tingkat']) {
                    KesehatanGelanggang::MERAH => "<fg=red>{$angka}</>",
                    KesehatanGelanggang::KUNING => "<fg=yellow>{$angka}</>",
                    default => "<fg=green>{$angka}</>",
                },
            );

            /*
             * Tindakannya disebutkan, bukan cuma angkanya. Panitia yang
             * membaca "320.000" tanpa tahu apa yang harus dikerjakan akan
             * menutup jendelanya dan melanjutkan pekerjaan lain.
             */
            if ($metrik['tindakan'] !== null) {
                $this->line('    '.$metrik['tindakan']);
            }
        }

        $this->newLine();

        return match ($hasil['tingkat']) {
            KesehatanGelanggang::MERAH => tap(self::FAILURE, fn () => $this->components->error('Gelanggang perlu ditangani sekarang.')),
            KesehatanGelanggang::KUNING => tap(self::SUCCESS, fn () => $this->components->warn('Ada yang perlu dikerjakan di jeda berikutnya.')),
            default => tap(self::SUCCESS, fn () => $this->components->info('Gelanggang sehat.')),
        };
    }
}
