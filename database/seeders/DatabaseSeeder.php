<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Urutannya penting: resource membuat permission, role membagikannya,
        // baru pengguna menerima role.
        $this->call([
            ResourceSeeder::class,
            RoleSeeder::class,
            SilatResourceSeeder::class,
            SilatRoleSeeder::class,
            SimulasiTurnamenSeeder::class,
        ]);

        /*
         * Satu akun bawaan saja.
         *
         * `admin@example.com` dan `user@example.com` ikut hilang bersama peran
         * boilerplate-nya (September 2026): keduanya tidak pernah dipakai
         * siapa pun, dan akun contoh berperan kosong justru membuat orang
         * mencoba masuk dengannya lalu mengira aplikasinya rusak.
         *
         * Akun petugas sungguhan -- ketua, pengendali, operator, juri, wasit,
         * official -- lahir di SimulasiTurnamenSeeder bersama kejuaraannya.
         */
        $this->account('Super Admin', 'super@example.com', config('resources.super_admin_role'));
    }

    private function account(string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $user->syncRoles([$role]);

        return $user;
    }
}
