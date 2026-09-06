<?php

namespace App\Console\Commands;

use App\Models\Arena;
use App\Models\User;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;
use Illuminate\Console\Command;

/**
 * Memindahkan kendali gelanggang dari Operator IT ke Pengendali Gelanggang.
 *
 * Tanpa perintah ini, hari kode baru terpasang tidak ada satu akun pun yang
 * boleh menjalankan timer: peran `operator-it` baru saja kehilangan wewenang
 * itu, dan `pengendali-gelanggang` belum dipegang siapa pun. Seluruh gelanggang
 * berhenti, dan yang muncul di panel cuma 403 tanpa penjelasan.
 *
 * Idempoten: aman dijalankan berkali-kali. Ia tidak pernah mencabut peran yang
 * sudah ada, hanya menambahkan yang kurang.
 */
class PindahkanPengendaliGelanggang extends Command
{
    protected $signature = 'silat:pindah-pengendali';

    protected $description = 'Memberi peran Pengendali Gelanggang kepada Operator IT yang sudah memegang gelanggang, lalu melaporkan gelanggang yang belum punya pengendali.';

    public function handle(): int
    {
        $this->components->info('Menyegarkan resource key dan peran.');

        $this->call('db:seed', ['--class' => SilatResourceSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => SilatRoleSeeder::class, '--force' => true]);

        $this->beriPeran();
        $this->salinPenugasan();
        $this->lupakanCacheIzin();

        return $this->laporkanGelanggangTanpaPengendali();
    }

    /**
     * Membuang cache izin Spatie setelah peran berubah.
     *
     * Tanpa langkah ini perintah ini meninggalkan persis gejala yang
     * dijanjikannya sembuh. Peran sudah tertulis di basis data, tapi yang
     * dibaca gate adalah peta izin yang ter-cache dari sebelum perintah
     * berjalan -- di dalamnya `pengendali-gelanggang` belum ada. Wewenang lama
     * masih lolos, wewenang baru ditolak, dan pengendali membuka panelnya
     * hanya untuk menemukan 403 yang tidak menjelaskan apa-apa.
     *
     * Terlihat pada 5 September 2026: `kendali-gelanggang.view` TOLAK
     * sementara `partai.view` IZIN, di akun yang jelas-jelas sudah punya
     * kedua izin itu di basis data.
     */
    private function lupakanCacheIzin(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info('Cache izin dibuang -- peran baru berlaku tanpa menunggu cache kedaluwarsa.');
    }

    /**
     * Setiap Operator IT ikut jadi Pengendali Gelanggang.
     *
     * Perannya yang lama TIDAK dicabut. Orang yang sama masih menjalankan
     * papan tampilan dan perangkat siaran; yang bertambah cuma wewenang
     * memimpin partai, sampai panitia memutuskan memisahkannya.
     */
    private function beriPeran(): void
    {
        $ditambah = 0;

        User::whereHas('roles', fn ($q) => $q->where('name', 'operator-it'))
            ->get()
            ->each(function (User $user) use (&$ditambah) {
                if ($user->hasRole('pengendali-gelanggang')) {
                    return;
                }

                $user->assignRole('pengendali-gelanggang');
                $ditambah++;
            });

        $this->components->info("{$ditambah} Operator IT diberi peran Pengendali Gelanggang.");
    }

    /**
     * Gelanggang yang sudah punya operator ikut punya pengendali.
     *
     * Migrasi sudah melakukannya sekali. Perintah ini mengulanginya untuk
     * gelanggang yang dibuat SESUDAH migrasi jalan -- kejuaraan baru yang
     * disusun panitia minggu berikutnya.
     */
    private function salinPenugasan(): void
    {
        $disalin = 0;

        Arena::with(['operators:id', 'pengendali:id'])->get()->each(function (Arena $arena) use (&$disalin) {
            $kurang = $arena->operators->pluck('id')->diff($arena->pengendali->pluck('id'));

            if ($kurang->isEmpty()) {
                return;
            }

            $arena->pengendali()->syncWithoutDetaching($kurang->all());
            $disalin += $kurang->count();
        });

        $this->components->info("{$disalin} penugasan gelanggang disalin dari operator ke pengendali.");
    }

    /**
     * Gelanggang tanpa pengendali dilaporkan, bukan diperbaiki diam-diam.
     *
     * Tidak ada tebakan yang aman di sini: menugaskan sembarang orang berarti
     * timer gelanggang dipegang seseorang yang tidak duduk di sana. Yang bisa
     * dilakukan perintah ini hanya memastikan panitia tahu sebelum hari-H,
     * bukan pada saat gong pertama.
     */
    private function laporkanGelanggangTanpaPengendali(): int
    {
        $kosong = Arena::aktif()->whereDoesntHave('pengendali')->get(['id', 'name']);

        if ($kosong->isEmpty()) {
            $this->components->info('Semua gelanggang aktif sudah punya pengendali.');

            return self::SUCCESS;
        }

        $this->components->warn('Gelanggang aktif yang BELUM punya pengendali:');

        foreach ($kosong as $arena) {
            $this->components->twoColumnDetail("#{$arena->id}", $arena->name);
        }

        $this->components->warn(
            'Tugaskan lewat menu Pertandingan → Gelanggang sebelum hari-H. '
            .'Gelanggang tanpa pengendali tidak bisa memulai babak.',
        );

        return self::FAILURE;
    }
}
