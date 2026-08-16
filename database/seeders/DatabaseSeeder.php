<?php

namespace Database\Seeders;

use App\Models\Post;
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
        ]);

        $this->account('Super Admin', 'super@example.com', config('resources.super_admin_role'));
        $this->account('Administrator', 'admin@example.com', 'admin');
        $this->account('Pengguna Biasa', 'user@example.com', 'user');
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
