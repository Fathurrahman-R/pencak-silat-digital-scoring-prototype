<?php

namespace App\Console\Commands;

use App\Support\Sinkron\CatatanKeluar;
use Illuminate\Console\Command;

/**
 * Menyemai catatan sinkron dari keadaan basis data sekarang.
 *
 * Catatan sinkron lahir dari observer: ia hanya berisi baris yang BERUBAH
 * sesudah observernya terpasang. Kejuaraan yang datanya sudah tersusun --
 * peserta diimpor, bagan disusun, jadwal ditetapkan -- karena itu punya
 * catatan yang nyaris kosong, dan node gelanggang yang baru dipasang menarik
 * dari nol lalu menerima anak tanpa induknya.
 *
 * Jalankan sekali di node global sebelum memasang laptop gelanggang pertama.
 * Node yang sudah pernah menyemai menolak menyemai lagi kecuali dipaksa:
 * menyemai dua kali menggandakan catatan tanpa menambah apa pun, karena
 * paketnya toh dipadatkan per baris.
 */
class SemaiSinkron extends Command
{
    protected $signature = 'silat:sinkron-semai {--paksa : Semai lagi walau node ini sudah pernah menyemai}';

    protected $description = 'Menyemai catatan sinkron dari keadaan basis data sekarang';

    public function handle(CatatanKeluar $catatan): int
    {
        if ($catatan->sudahDisemai() && ! $this->option('paksa')) {
            $this->components->info('Node ini sudah pernah menyemai catatan sinkronnya. Pakai --paksa kalau memang perlu diulang.');

            return self::SUCCESS;
        }

        $this->components->info('Menyemai catatan sinkron dari keadaan sekarang…');

        $jumlah = $catatan->semai();

        $this->components->info("{$jumlah} baris disemai. Kursor terakhir: {$catatan->kursorTerakhir()}.");

        if ($jumlah === 0) {
            $this->components->warn(
                'Tidak ada satu baris pun yang dimiliki node ini. '
                .'Periksa SINKRON_PERAN dan SINKRON_ARENA di .env — node global mengisi arena kosong.',
            );
        }

        return self::SUCCESS;
    }
}
