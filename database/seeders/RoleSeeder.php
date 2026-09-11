<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(
            ['name' => config('resources.super_admin_role'), 'guard_name' => 'web'],
            [
                'label' => 'Super Admin',
                'description' => 'Melewati seluruh pengecekan permission. Tidak bisa dihapus.',
                'is_locked' => true,
            ],
        );

        // Sengaja tanpa permission apa pun: super admin dilewatkan lewat
        // Gate::before, bukan lewat daftar centang.
        $superAdmin->syncPermissions([]);

        /*
         * Hanya super admin yang lahir di sini.
         *
         * Dua peran bawaan boilerplate — `admin` dan `user` — dibuang
         * September 2026. `admin` cuma memegang `users.*`, dan pekerjaan itu
         * sekarang ada di Operator IT bersama seluruh administrasi kejuaraan;
         * `user` tidak memegang apa pun sejak awal. Keduanya nol pengguna dan
         * tidak dirujuk kode mana pun, dan yang tersisa cuma dua baris di
         * panel Peran yang membuat panitia mengira ada dua tingkat
         * administrasi di atas peran aparat.
         *
         * Akun yang belum ditugaskan tidak diberi peran sama sekali. Itu bukan
         * kemunduran: `user` memang tidak pernah memberi kewenangan apa pun,
         * jadi "tanpa peran" dan "berperan user" sama saja — bedanya sekarang
         * keadaannya terbaca apa adanya di panel Pengguna.
         *
         * Peran domain pencak silat didaftarkan terpisah di SilatRoleSeeder,
         * yang berjalan setelah resource domainnya ada. Pembubaran kedua peran
         * ini pada pemasangan yang sudah jalan juga ditangani di sana.
         */
    }
}
