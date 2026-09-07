<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Database\Seeders\KejuaraanSkalaSeeder;
use Database\Seeders\SimulasiTurnamenSeeder;
use Illuminate\Console\Command;

/**
 * Pembungkus SimulasiTurnamenSeeder untuk uji coba manual.
 *
 * Berdiri sebagai perintah sendiri, bukan cuma `db:seed --class=...`, karena
 * yang paling sering dibutuhkan saat menguji manual justru mengulang dari
 * bersih: data simulasi sudah kotor oleh percobaan sebelumnya dan harus
 * dibuang dulu. Tanpa perintah ini, membuangnya berarti mengetik satu baris
 * tinker yang panjang dan mudah salah.
 */
class SimulasiCommand extends Command
{
    protected $signature = 'silat:simulasi
                            {--skala=kecil : kecil (100 pesilat, 5 kelas), sedang (semua kelas, 2 gelanggang), besar (semua kelas, 3 gelanggang)}
                            {--tanpa-bagan : Berhenti sesudah pendaftaran lunas dan sah, tanpa menyusun bagan dan jadwal}
                            {--reset : Hapus kejuaraan dengan skala yang sama lebih dulu}';

    protected $description = 'Menyiapkan kejuaraan siap-uji: kecil untuk menelusuri satu partai, sedang dan besar untuk menguji sistem seukuran kejuaraan sungguhan';

    public function handle(): int
    {
        $skala = (string) $this->option('skala');

        if (! in_array($skala, ['kecil', 'sedang', 'besar'], strict: true)) {
            $this->error("Skala tidak dikenal: {$skala}. Pilih kecil, sedang, atau besar.");

            return self::FAILURE;
        }

        /*
         * Tiap skala punya slugnya sendiri, jadi ketiganya boleh berdiri
         * bersamaan di satu basis data: panitia bisa berlatih pada data besar
         * tanpa membuang kejuaraan kecil yang sedang dipakai menelusuri satu
         * partai.
         */
        $slug = match ($skala) {
            'kecil' => SimulasiTurnamenSeeder::SLUG,
            'sedang' => KejuaraanSkalaSeeder::SLUG_SEDANG,
            'besar' => KejuaraanSkalaSeeder::SLUG_BESAR,
        };

        if ($skala === 'kecil' && $this->option('tanpa-bagan')) {
            $this->error('--tanpa-bagan hanya berlaku untuk skala sedang dan besar.');
            $this->line('Skala kecil memang disiapkan untuk menelusuri partai yang sudah terjadwal.');

            return self::FAILURE;
        }

        $lama = Tournament::withTrashed()->where('slug', $slug)->first();

        if ($lama !== null && ! $this->option('reset')) {
            $this->warn("Kejuaraan skala {$skala} #{$lama->id} sudah ada.");
            $this->line('Jalankan ulang dengan --reset untuk membuangnya dan menyusun data yang bersih.');

            return self::FAILURE;
        }

        if ($lama !== null) {
            if ($this->getOutput()->isVerbose() || $this->input->isInteractive()) {
                $this->warn("Kejuaraan simulasi #{$lama->id} beserta seluruh peserta, tagihan, bagan, dan hasilnya akan dihapus permanen.");

                if (! $this->confirm('Lanjutkan?', default: false)) {
                    return self::FAILURE;
                }
            }

            $lama->forceDelete();
            $this->info("Kejuaraan simulasi #{$lama->id} dihapus.");
        }

        if ($skala === 'kecil') {
            $this->call('db:seed', ['--class' => SimulasiTurnamenSeeder::class, '--force' => true]);

            return self::SUCCESS;
        }

        /*
         * Dijalankan langsung, bukan lewat `db:seed --class`: skala dan saklar
         * bagannya adalah parameter, dan db:seed tidak punya jalan untuk
         * meneruskannya.
         */
        $seeder = app(KejuaraanSkalaSeeder::class);
        $seeder->setCommand($this);
        $seeder->skala($skala)->tanpaBagan((bool) $this->option('tanpa-bagan'))->run();

        return self::SUCCESS;
    }
}
