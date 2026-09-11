<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Resource, permission, dan peran -- disemai SEKALI untuk seluruh rangkaian uji.
 *
 * Sebelum ini tiap test memanggil keempat seeder di bawah pada `beforeEach`,
 * dan itulah hampir seluruh biaya rangkaiannya: satu berkas berisi lima test
 * memakan tiga puluh detik, sementara badan kelima test-nya sendiri cuma nol
 * koma enam detik. Sisanya menyemai ratusan permission yang isinya sama
 * persis, seribu dua ratus kali berturut-turut.
 *
 * Dipanggil dari `Tests\TestCase::migrateFreshUsing()`, artinya ia berjalan
 * bersama `migrate:fresh` -- sekali per proses uji, dan DI LUAR transaksi yang
 * dibuka RefreshDatabase untuk tiap test. Karena itu ia tidak pernah ikut
 * diputar kembali, sementara perubahan yang dibuat sebuah test terhadap peran
 * tetap hilang begitu test-nya selesai.
 */
class BasisUjiSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ResourceSeeder::class,
            RoleSeeder::class,
            SilatResourceSeeder::class,
            SilatRoleSeeder::class,
        ]);
    }
}
