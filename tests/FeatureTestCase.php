<?php

namespace Tests;

use Database\Seeders\BasisUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dasar uji yang menyentuh basis data.
 *
 * # Kenapa trait-nya dipakai di SINI, bukan di Pest.php
 *
 * Method milik trait mengalahkan method yang diwarisi dari kelas induk. Selama
 * `RefreshDatabase` dipasang di kelas yang dibangkitkan Pest, tidak ada satu
 * pun kait-nya yang bisa diubah dari `Tests\TestCase` -- override-nya tidak
 * menghasilkan galat, ia cuma tidak pernah dipanggil. Dicoba, dan diam.
 *
 * Dipasang di kelas ini, aturannya berbalik: method yang ditulis di dalam
 * kelas mengalahkan method trait-nya sendiri.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Resource, permission, dan peran disemai bersama `migrate:fresh`.
     *
     * RefreshDatabase menjalankan migrasi SEKALI per proses lalu membungkus
     * tiap test dalam transaksi yang diputar kembali. Seeder yang ikut di sini
     * berjalan sebelum transaksi pertama dibuka, jadi isinya berdiri untuk
     * seluruh rangkaian -- sementara perubahan yang dibuat sebuah test
     * terhadap peran tetap hilang begitu test-nya selesai.
     *
     * Sebelumnya keempat seeder itu dipanggil di `beforeEach` enam puluh
     * delapan berkas uji. Itulah hampir seluruh biaya rangkaiannya: satu
     * berkas berisi lima test memakan tiga puluh detik, sementara badan
     * kelima test-nya cuma nol koma enam detik. Sisanya menyemai ratusan
     * permission yang isinya sama persis, seribu dua ratus kali.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--seed' => true,
            '--seeder' => BasisUjiSeeder::class,
        ];
    }
}
