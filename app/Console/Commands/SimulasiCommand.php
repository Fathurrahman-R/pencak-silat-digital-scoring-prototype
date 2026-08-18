<?php

namespace App\Console\Commands;

use App\Models\Tournament;
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
                            {--reset : Hapus kejuaraan simulasi yang ada lebih dulu}';

    protected $description = 'Menyiapkan kejuaraan siap-uji untuk simulasi manual';

    public function handle(): int
    {
        $lama = Tournament::withTrashed()->where('slug', SimulasiTurnamenSeeder::SLUG)->first();

        if ($lama !== null && ! $this->option('reset')) {
            $this->warn("Kejuaraan simulasi #{$lama->id} sudah ada.");
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

        $this->call('db:seed', ['--class' => SimulasiTurnamenSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
