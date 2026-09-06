<?php

namespace App\Console\Commands;

use App\Models\SilatMatch;
use App\Support\Scoring\SnapshotSkor;
use Illuminate\Console\Command;

/**
 * Menyusun ulang snapshot skor seluruh partai.
 *
 * Snapshot membatalkan dirinya sendiri tiap nilai berubah, jadi dalam keadaan
 * normal perintah ini tidak dibutuhkan. Ia ada untuk keadaan yang tidak
 * normal: baris nilai yang disunting langsung di basis data, pemulihan dari
 * cadangan, atau kecurigaan bahwa angka di layar tidak lagi cocok dengan
 * bahannya.
 *
 * Membuang lebih dulu, baru menghitung. Kalau prosesnya terputus di tengah,
 * yang tertinggal adalah partai tanpa snapshot -- yang berarti dihitung ulang
 * saat dibaca, dan itu selalu benar. Menghitung lebih dulu berarti yang
 * tertinggal adalah campuran snapshot lama dan baru, dan tidak ada cara
 * membedakannya.
 */
class SnapshotSkorCommand extends Command
{
    protected $signature = 'silat:snapshot-skor
                            {--bangun-ulang : Hitung ulang seluruhnya, jangan cuma yang kosong}
                            {--turnamen= : Batasi ke satu kejuaraan}';

    protected $description = 'Menyusun ulang snapshot skor partai dari score_events dan penalties';

    public function handle(SnapshotSkor $snapshot): int
    {
        $kueri = SilatMatch::query()
            ->when($this->option('turnamen'), fn ($q, $id) => $q->whereHas(
                'bracket.weightClass',
                fn ($w) => $w->where('tournament_id', $id),
            ));

        if (! $this->option('bangun-ulang')) {
            $kueri->whereNull('snapshot_pada');
        }

        $jumlah = (clone $kueri)->count();

        if ($jumlah === 0) {
            $this->components->info('Tidak ada partai yang perlu dihitung.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($jumlah);
        $bar->start();

        $kueri->chunkById(100, function ($partai) use ($snapshot, $bar) {
            foreach ($partai as $match) {
                $snapshot->bangunUlang($match);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info(number_format($jumlah).' partai dihitung ulang.');

        return self::SUCCESS;
    }
}
