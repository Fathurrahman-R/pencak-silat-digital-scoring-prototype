<?php

namespace App\Support\Navigation;

use App\Enums\StatusTurnamen;
use App\Http\Middleware\IngatTurnamenAktif;
use App\Models\Tournament;
use App\Support\Resources\ResourceGate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun menu sidebar dari config/navigation.php, membuang item yang
 * resource key-nya tidak dimiliki pengguna.
 *
 * Keluarannya DATAR: satu deret entri, masing-masing berupa item tunggal atau
 * seksi berisi item. Tidak ada pohon bercabang, karena sidebarnya sendiri
 * tidak lagi punya grup yang bisa dilipat.
 *
 * Seksi yang seluruh itemnya tersembunyi ikut hilang — judul seksi tanpa isi
 * hanya membuat orang mengira ada yang gagal dimuat.
 */
class NavigationBuilder
{
    private bool $turnamenDicari = false;

    private ?Tournament $turnamen = null;

    public function __construct(private readonly ResourceGate $gate) {}

    /**
     * @return array<int, array<string, mixed>>
     *
     * Tiap entri berbentuk salah satu dari:
     *   ['tipe' => 'item',  'label', 'icon', 'url', 'active', 'badge']
     *   ['tipe' => 'seksi', 'label', 'items' => array<item>]
     */
    public function build(): array
    {
        $hasil = [];

        foreach (config('navigation', []) as $entri) {
            if (isset($entri['seksi'])) {
                $items = $this->saring($entri['items'] ?? []);

                if ($items === []) {
                    continue;
                }

                $hasil[] = [
                    'tipe' => 'seksi',
                    'label' => $entri['seksi'],
                    'items' => $items,
                ];

                continue;
            }

            $item = $this->bentuk($entri);

            if ($item !== null) {
                $hasil[] = $item;
            }
        }

        return $hasil;
    }

    /**
     * Seluruh item yang bisa dibuka pengguna, tanpa judul seksinya.
     *
     * Dipakai layar yang butuh daftar tujuan tanpa peduli pengelompokannya --
     * pencarian menu (⌘K) salah satunya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function semuaItem(): array
    {
        $hasil = [];

        foreach ($this->build() as $entri) {
            if ($entri['tipe'] === 'seksi') {
                foreach ($entri['items'] as $item) {
                    $hasil[] = $item + ['seksi' => $entri['label']];
                }

                continue;
            }

            $hasil[] = $entri + ['seksi' => null];
        }

        return $hasil;
    }

    /**
     * Kejuaraan yang sedang dibuka.
     *
     * Diambil ulang dari basis data, bukan disalin ke sesi, supaya nama yang
     * berubah atau kejuaraan yang dihapus tidak meninggalkan sisa di menu.
     *
     * Sesi hanya menyimpan kejuaraan yang terakhir dibuka. Selama belum ada,
     * menunya tidak boleh menunggu — pengguna yang baru masuk akan melihat
     * sidebar yang isinya cuma tiga menu, dan tidak ada petunjuk bahwa
     * sisanya baru muncul setelah sebuah kejuaraan dibuka. Karena itu
     * kejuaraan termuda dipakai sebagai bawaan.
     *
     * Hasilnya ditahan satu request: satu halaman memanggil ini sekali per
     * item menu.
     */
    public function turnamenAktif(): ?Tournament
    {
        if ($this->turnamenDicari) {
            return $this->turnamen;
        }

        $this->turnamenDicari = true;

        $id = session(IngatTurnamenAktif::KUNCI);

        $this->turnamen = ($id ? Tournament::find($id) : null) ?? $this->turnamenBawaan();

        return $this->turnamen;
    }

    /**
     * Kejuaraan yang paling masuk akal dibuka lebih dulu: yang sedang
     * berjalan, lalu draf, baru yang sudah selesai.
     */
    private function turnamenBawaan(): ?Tournament
    {
        return Tournament::query()
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                StatusTurnamen::Berjalan->value,
                StatusTurnamen::Draf->value,
            ])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function saring(array $items): array
    {
        $hasil = [];

        foreach ($items as $item) {
            $bentuk = $this->bentuk($item);

            if ($bentuk !== null) {
                $hasil[] = $bentuk;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function bentuk(array $item): ?array
    {
        if (isset($item['resource']) && ! $this->gate->any((array) $item['resource'])) {
            return null;
        }

        /*
         * Menu yang kehilangan gunanya saat sebuah saklar menyala.
         *
         * Nilainya kunci config, bukan closure, supaya berkas navigasi tetap
         * bisa dilewatkan `config:cache`. Rutenya sendiri tidak ikut hilang:
         * pendaftaran lama yang telanjur masuk antrean masih harus bisa
         * diputuskan lewat alamatnya.
         */
        if (isset($item['sembunyi_bila']) && config($item['sembunyi_bila'])) {
            return null;
        }

        /*
         * Item yang butuh kejuaraan aktif tidak bisa dibentuk alamatnya sebelum
         * ada kejuaraan yang dibuka, jadi disembunyikan seluruhnya — bukan
         * ditampilkan sebagai tautan mati.
         */
        if (($item['butuh_turnamen'] ?? false) && $this->turnamenAktif() === null) {
            return null;
        }

        return [
            'tipe' => 'item',
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'url' => $this->url($item),
            'active' => $this->sedangDibuka($item),
            'badge' => $item['badge'] ?? null,
        ];
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

    /** @param  array<string, mixed>  $item */
    private function sedangDibuka(array $item): bool
    {
        if (isset($item['active'])) {
            return Request::is($item['active']);
        }

        if (isset($item['route'])) {
            return Request::routeIs($item['route']);
        }

        return false;
    }
}
