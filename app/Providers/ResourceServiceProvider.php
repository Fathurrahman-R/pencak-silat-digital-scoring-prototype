<?php

namespace App\Providers;

use App\Support\Resources\ResourceGate;
use App\Support\Resources\ResourceManager;
use App\Support\Resources\ResourceMap;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ResourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceMap::class);
        $this->app->singleton(ResourceGate::class);
        $this->app->singleton(ResourceManager::class);
    }

    public function boot(): void
    {
        $this->registerSuperAdminBypass();
        $this->registerBladeDirectives();
    }

    /**
     * Super admin lolos seluruh pengecekan Gate, termasuk policy dan
     * permission Spatie. Mengembalikan null (bukan false) saat bukan super
     * admin supaya pemeriksaan berikutnya tetap berjalan seperti biasa.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            return app(ResourceGate::class)->isSuperAdmin($user) ? true : null;
        });
    }

    /**
     * Tiga directive, semuanya memakai resource key yang sama dengan route:
     *
     *   @resource('turnamen.update') ... @endresource
     *
     *   @anyresource(['turnamen.update', 'turnamen.delete']) ... @endanyresource
     *
     *   @allresource(['turnamen.view', 'turnamen.export']) ... @endallresource
     *
     * Blade::if ikut menyediakan bentuk @else... dan @unless... untuk ketiganya.
     *
     * Catatan: Blade mengompilasi @elseresource menjadi pemanggilan tanpa
     * argumen. Karena itu setiap callback mengembalikan true saat dipanggil
     * tanpa key — itulah cabang "else" biasa.
     */
    private function registerBladeDirectives(): void
    {
        Blade::if('resource', fn (?string $key = null): bool => $key === null
            || app(ResourceGate::class)->allows($key));

        Blade::if('anyresource', fn (array|string|null $keys = null): bool => $keys === null
            || app(ResourceGate::class)->any($keys));

        Blade::if('allresource', fn (array|string|null $keys = null): bool => $keys === null
            || app(ResourceGate::class)->all($keys));
    }
}
