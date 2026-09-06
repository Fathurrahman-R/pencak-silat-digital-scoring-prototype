<?php

namespace App\Console\Commands;

use App\Support\Arsip\PemangkasRiwayatJuri;
use App\Support\Arsip\PendorongArsip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mendorong arsip yang tertunda, dan memangkas riwayat yang sudah aman
 * dipangkas.
 *
 * Dua pekerjaan yang berbeda sifatnya sengaja dipisah jadi dua opsi, bukan
 * dijalankan berurutan otomatis: yang pertama menambah salinan bukti, yang
 * kedua MENGHAPUS bukti. Menyatukannya berarti satu perintah yang salah
 * ketik bisa menghapus riwayat yang baru saja gagal dikirim.
 */
class ArsipCommand extends Command
{
    protected $signature = 'silat:arsip
                            {--dorong : Kirim ulang arsip partai yang belum sampai ke node global}
                            {--pangkas : Buang riwayat juri partai yang arsipnya sudah dikonfirmasi}
                            {--batas=50 : Berapa partai yang diproses sekali jalan}';

    protected $description = 'Mengirim arsip bukti partai ke node global, dan memangkas riwayat juri yang sudah aman dibuang';

    public function handle(PendorongArsip $pendorong, PemangkasRiwayatJuri $pemangkas): int
    {
        if (! $this->option('dorong') && ! $this->option('pangkas')) {
            $this->laporkanKeadaan();

            return self::SUCCESS;
        }

        $batas = (int) $this->option('batas');

        if ($this->option('dorong')) {
            $hasil = $pendorong->sapu($batas);
            $this->components->info("Arsip: {$hasil['berhasil']} dari {$hasil['dicoba']} partai berhasil dikirim.");
        }

        if ($this->option('pangkas')) {
            $hasil = $pemangkas->pangkas($batas);

            $this->components->info(
                "Pangkas: {$hasil['dipangkas']} partai, ".number_format($hasil['baris_dibuang']).' baris riwayat juri dibuang.'
            );

            /*
             * Yang dilewati disebutkan satu per satu, bukan diringkas jadi
             * angka. Tiap barisnya adalah partai yang buktinya masih cuma ada
             * di laptop ini, dan alasannya menentukan apa yang harus
             * dikerjakan panitia -- node global tidak terjangkau butuh
             * tindakan yang berbeda dari checksum yang tidak cocok.
             */
            foreach ($hasil['dilewati'] as $alasan) {
                $this->components->warn($alasan);
            }
        }

        return self::SUCCESS;
    }

    private function laporkanKeadaan(): void
    {
        $tertunda = DB::table('arsip_keluar')->where('status', '!=', PendorongArsip::DITERIMA)->count();
        $diterima = DB::table('arsip_keluar')->where('status', PendorongArsip::DITERIMA)->count();
        $siapPangkas = DB::table('matches')
            ->whereNotNull('ratified_at')
            ->whereNull('judge_inputs_dipangkas_pada')
            ->whereIn('id', DB::table('arsip_keluar')->where('status', PendorongArsip::DITERIMA)->pluck('match_id'))
            ->count();

        $this->components->twoColumnDetail('Arsip belum sampai', (string) $tertunda);
        $this->components->twoColumnDetail('Arsip sudah diterima', (string) $diterima);
        $this->components->twoColumnDetail('Siap dipangkas', (string) $siapPangkas);
        $this->newLine();
        $this->line('  Jalankan dengan --dorong untuk mengirim yang tertunda, --pangkas untuk membuang riwayat juri yang sudah aman.');
    }
}
