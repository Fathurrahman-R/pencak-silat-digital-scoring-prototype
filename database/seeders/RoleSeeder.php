<?php

namespace Database\Seeders;

use App\Enums\ResourceAction;
use App\Models\Permission;
use App\Models\Resource;
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

        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            [
                'label' => 'Administrator',
                'description' => 'Mengelola pengguna dan konten, tanpa menyentuh struktur hak akses.',
            ],
        );

        $admin->syncPermissions($this->permissionsFor([
            'users' => [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Export],
        ]));

        $user = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['label' => 'Pengguna', 'description' => 'Bisa masuk, belum memegang kewenangan apa pun.'],
        );

        /*
         * Sengaja tanpa permission. Ini peran dasar bagi orang yang sudah punya
         * akun tetapi belum ditugaskan sebagai aparat atau official — memberinya
         * kewenangan bawaan berarti setiap akun baru langsung bisa melihat data
         * peserta sebelum ada yang memutuskan demikian.
         *
         * Peran domain pencak silat didaftarkan terpisah di SilatRoleSeeder,
         * yang berjalan setelah resource domainnya ada.
         */
        $user->syncPermissions([]);
    }

    /**
     * Mengambil permission lewat resource key-nya, bukan lewat nama permission.
     * Kalau pemetaannya diubah nanti, seeder ini tetap menunjuk hal yang benar.
     *
     * @param  array<string, array<int, ResourceAction>>  $map
     * @return array<int, Permission>
     */
    private function permissionsFor(array $map): array
    {
        $permissions = [];

        foreach ($map as $resourceKey => $actions) {
            $resource = Resource::where('key', $resourceKey)->with('mappings.permission')->first();

            if ($resource === null) {
                continue;
            }

            foreach ($actions as $action) {
                $mapping = $resource->mappings->firstWhere('action', $action);

                if ($mapping?->permission) {
                    $permissions[] = $mapping->permission;
                }
            }
        }

        return $permissions;
    }
}
