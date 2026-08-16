<?php

namespace App\Support\Navigation;

use App\Http\Middleware\IngatTurnamenAktif;
use App\Models\Tournament;
use App\Support\Resources\ResourceGate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun menu sidebar dari config/navigation.php, membuang item yang
 * resource key-nya tidak dimiliki pengguna.
 *
 * Induk yang seluruh anaknya tersembunyi ikut hilang — kalau tidak, pengguna
 * melihat grup menu kosong yang tidak bisa dibuka.
 */
class NavigationBuilder
{
    public function __construct(private readonly ResourceGate $gate) {}

    /** @return array<int, array<string, mixed>> */
    public function build(): array
    {
        return $this->filter(config('navigation', []));
    }

    /**
     * Kejuaraan yang sedang dibuka, kalau ada.
     *
     * Diambil ulang dari basis data tiap kali, bukan disalin ke sesi, supaya
     * nama yang berubah atau kejuaraan yang dihapus tidak meninggalkan sisa di
     * menu.
     */
    public function turnamenAktif(): ?Tournament
    {
        $id = session(IngatTurnamenAktif::KUNCI);

        return $id ? Tournament::find($id) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filter(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! $this->visible($item)) {
                continue;
            }

            /*
             * Item yang butuh kejuaraan aktif tidak bisa dibentuk alamatnya
             * sebelum ada kejuaraan yang dibuka, jadi disembunyikan seluruhnya
             * — bukan ditampilkan sebagai tautan mati.
             */
            if (($item['butuh_turnamen'] ?? false) && $this->turnamenAktif() === null) {
                continue;
            }

            $children = $this->filter($item['children'] ?? []);

            if (isset($item['children']) && $children === []) {
                continue;
            }

            $result[] = [
                'label' => $this->label($item),
                'icon' => $item['icon'] ?? null,
                'url' => $this->url($item),
                'active' => $this->isActive($item, $children),
                'children' => $children,
                'badge' => $item['badge'] ?? null,
            ];
        }

        return $result;
    }

    /** @param  array<string, mixed>  $item */
    private function visible(array $item): bool
    {
        if (isset($item['resource'])) {
            return $this->gate->any((array) $item['resource']);
        }

        // Item tanpa resource key selalu tampil (mis. Dashboard).
        return true;
    }

    /**
     * Label yang boleh berupa nama kejuaraan yang sedang dibuka.
     *
     * @param  array<string, mixed>  $item
     */
    private function label(array $item): string
    {
        return ($item['label_turnamen'] ?? false)
            ? ($this->turnamenAktif()?->name ?? $item['label'])
            : $item['label'];
    }

    /** @param  array<string, mixed>  $item */
    private function url(array $item): ?string
    {
        if (isset($item['url'])) {
            return $item['url'];
        }

        if (! isset($item['route']) || ! Route::has($item['route'])) {
            return null;
        }

        $params = $item['route_params'] ?? [];

        if ($item['butuh_turnamen'] ?? false) {
            $tournament = $this->turnamenAktif();

            if ($tournament === null) {
                return null;
            }

            $params = ['tournament' => $tournament, ...$params];
        }

        return route($item['route'], $params);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, mixed>>  $children
     */
    private function isActive(array $item, array $children): bool
    {
        foreach ($children as $child) {
            if ($child['active']) {
                return true;
            }
        }

        if (isset($item['active'])) {
            return Request::is($item['active']);
        }

        if (isset($item['route'])) {
            return Request::routeIs($item['route']);
        }

        return false;
    }
}
