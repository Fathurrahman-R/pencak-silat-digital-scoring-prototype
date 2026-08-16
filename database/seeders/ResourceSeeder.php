<?php

namespace Database\Seeders;

use App\Enums\ResourceAction;
use App\Models\Resource;
use App\Support\Resources\ResourceManager;
use Illuminate\Database\Seeder;

/**
 * Resource inti panel admin.
 *
 * Semuanya ditandai terkunci: boleh diubah label dan pemetaannya, tapi tidak
 * boleh dihapus dari UI. Tanpa penjagaan ini, satu klik bisa menghapus
 * resource "roles" dan menutup jalan untuk memperbaikinya lewat panel.
 */
class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(ResourceManager::class);

        foreach ($this->definitions() as $definition) {
            if (Resource::where('key', $definition['key'])->exists()) {
                continue;
            }

            $resource = $manager->createResource([
                'key' => $definition['key'],
                'label' => $definition['label'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'is_locked' => $definition['locked'] ?? false,
            ], $definition['actions']);

            if ($definition['locked'] ?? false) {
                // Permission inti ikut dikunci supaya tidak terhapus dari modul
                // Permission dan membuat key-nya menggantung.
                $resource->mappings()->with('permission')->get()
                    ->each(fn ($mapping) => $mapping->permission?->update(['is_locked' => true]));
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $crud = [ResourceAction::View, ResourceAction::Create, ResourceAction::Update, ResourceAction::Delete];

        return [
            [
                'key' => 'users',
                'label' => 'Pengguna',
                'group' => 'Manajemen Akses',
                'description' => 'Akun pengguna dan role yang dimilikinya.',
                'actions' => [...$crud, ResourceAction::Export],
                'locked' => true,
            ],
            [
                'key' => 'roles',
                'label' => 'Role',
                'group' => 'Manajemen Akses',
                'description' => 'Kumpulan permission yang ditugaskan ke pengguna.',
                'actions' => $crud,
                'locked' => true,
            ],
            [
                'key' => 'permissions',
                'label' => 'Permission',
                'group' => 'Manajemen Akses',
                'description' => 'Izin mentah yang ditunjuk resource key.',
                'actions' => $crud,
                'locked' => true,
            ],
            [
                'key' => 'resources',
                'label' => 'Resource',
                'group' => 'Manajemen Akses',
                'description' => 'Daftar resource yang menghasilkan resource key.',
                'actions' => $crud,
                'locked' => true,
            ],
            [
                'key' => 'mappings',
                'label' => 'Pemetaan Key',
                'group' => 'Manajemen Akses',
                'description' => 'Menentukan permission di balik tiap resource key.',
                'actions' => [ResourceAction::View, ResourceAction::Update],
                'locked' => true,
            ],
        ];
    }
}
