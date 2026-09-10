<?php

namespace App\Console\Commands;

use App\Support\Resources\ResourceMap;
use Illuminate\Console\Command;
use Illuminate\Routing\Route as RouteObjek;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Audit kewenangan: apakah tiap resource key punya pemilik, dan apakah tiap
 * pemilik bisa MENCAPAI layar yang memakainya.
 *
 * Uji kotak hitam September 2026 menemukan tiga lapisan cacat yang sama-sama
 * tak bersuara di log maupun di suite:
 *
 *   1. key tanpa pemilik           -- tombolnya tidak pernah tergambar;
 *   2. pemilik tanpa tombol        -- sisi servernya ada, pintunya tidak;
 *   3. pemilik tanpa jalan         -- tombolnya ada, tapi halaman yang
 *                                     memuatnya dijaga key lain yang tidak
 *                                     ia punya, jadi ia dapat 403.
 *
 * Ketiganya lolos dari seluruh suite karena uji yang ada memakai super-admin,
 * yang melewati pemeriksaan lewat Gate::before.
 */
class AuditIzin extends Command
{
    protected $signature = 'silat:audit-izin {--json : Keluarkan sebagai JSON}';

    protected $description = 'Memeriksa kepemilikan dan keterjangkauan tiap resource key';

    public function handle(ResourceMap $peta): int
    {
        $peran = Role::with('permissions')->get()->reject(fn (Role $r) => $r->name === 'super-admin');

        $pemilik = [];   // key => [nama peran]
        foreach ($peta->all() as $key => $permission) {
            $pemilik[$key] = $permission === null
                ? []
                : $peran->filter(fn (Role $r) => $r->permissions->contains('name', $permission))
                    ->pluck('name')->values()->all();
        }

        $rute = $this->ruteBerpenjaga();
        [$keyPasti, $keyMungkin] = $this->keyDiKode();
        $blade = array_values(array_unique(array_merge($keyPasti, $keyMungkin)));

        $temuan = [
            'key_tanpa_permission' => $peta->unmappedKeys(),
            'key_tanpa_pemilik' => [],
            'key_tak_dikenal_di_rute' => [],
            'key_tak_dikenal_di_tampilan' => [],
            'key_tanpa_permukaan' => [],
            'key_terdaftar_tak_terpakai' => [],
            'pemilik_tanpa_jalan' => [],
        ];

        // 1. Key yang dipakai rute atau tampilan tapi tidak dimiliki siapa pun.
        foreach ($peta->all() as $key => $permission) {
            $dipakai = isset($rute['per_key'][$key]) || in_array($key, $blade, true);

            if ($pemilik[$key] === [] && $dipakai) {
                $temuan['key_tanpa_pemilik'][] = $key;
            }

            // 2. Key berpemilik yang tidak dipakai satu permukaan pun.
            if ($pemilik[$key] !== [] && ! $dipakai) {
                $temuan['key_tanpa_permukaan'][] = $key;
            }

            // 3. Key yang tidak dimiliki siapa pun DAN tidak dijaga apa pun:
            //    entri peta yang tidak berakibat apa-apa.
            if ($pemilik[$key] === [] && ! $dipakai) {
                $temuan['key_terdaftar_tak_terpakai'][] = $key;
            }
        }

        // 3. Key yang disebut rute/tampilan tapi tidak terdaftar di peta:
        //    ResourceGate menolaknya dan hanya menulis peringatan sekali.
        foreach (array_keys($rute['per_key']) as $key) {
            if (! $peta->has($key)) {
                $temuan['key_tak_dikenal_di_rute'][] = $key;
            }
        }

        /*
         * Hanya yang lahir dari `rk()` yang diperiksa di sini. Key harfiah
         * ikut terjaring nama rute seperti 'password.update', dan
         * melaporkannya sebagai key tak dikenal cuma bising.
         */
        foreach ($keyPasti as $key) {
            if (! $peta->has($key)) {
                $temuan['key_tak_dikenal_di_tampilan'][] = $key;
            }
        }

        /*
         * 4. Pemilik tanpa jalan.
         *
         * Sebuah peran memiliki key K, tapi tiap rute yang menuntut K juga
         * menuntut key lain yang tidak ia punya -- jadi K tidak pernah bisa ia
         * pakai. Inilah bentuk cacat Sekretariat vs `nomor-jurus.update`.
         *
         * Key yang sama sekali tidak dijaga rute mana pun dilewati: ia dipakai
         * lewat @resource di dalam halaman, dan keterjangkauannya ditentukan
         * halaman itu, bukan dirinya sendiri.
         */
        foreach ($rute['per_key'] as $key => $daftarRute) {
            foreach ($pemilik[$key] ?? [] as $namaPeran) {
                $bisa = false;

                foreach ($daftarRute as $satu) {
                    if ($this->peranMemenuhi($namaPeran, $satu['keys'], $peta, $peran)) {
                        $bisa = true;
                        break;
                    }
                }

                if (! $bisa) {
                    $temuan['pemilik_tanpa_jalan'][] = [
                        'key' => $key,
                        'peran' => $namaPeran,
                        'rute' => array_column($daftarRute, 'nama'),
                    ];
                }
            }
        }

        return $this->option('json')
            ? $this->keluarkanJson($temuan, $pemilik)
            : $this->keluarkanTeks($temuan, $pemilik, $rute);
    }

    /**
     * Rute yang dijaga middleware `resource:`, beserta SELURUH key yang
     * dituntutnya. Satu segmen boleh berisi beberapa key dipisah `|` -- itu
     * "salah satu", sementara segmen terpisah berarti "semuanya".
     *
     * @return array{per_key: array<string, array<int, array{nama: string, keys: array<int, array<int, string>>}>>, jumlah: int}
     */
    private function ruteBerpenjaga(): array
    {
        $perKey = [];
        $jumlah = 0;

        foreach (Route::getRoutes() as $satu) {
            /** @var RouteObjek $satu */
            $segmen = [];

            foreach ($satu->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'resource:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, strlen('resource:'))) as $bagian) {
                    $segmen[] = array_values(array_filter(array_map('trim', explode('|', $bagian))));
                }
            }

            if ($segmen === []) {
                continue;
            }

            $jumlah++;
            $nama = $satu->getName() ?? $satu->uri();

            foreach ($segmen as $satuSegmen) {
                foreach ($satuSegmen as $key) {
                    $perKey[$key][] = ['nama' => $nama, 'keys' => $segmen];
                }
            }
        }

        return ['per_key' => $perKey, 'jumlah' => $jumlah];
    }

    /**
     * Apakah peran memenuhi seluruh segmen penjaga sebuah rute.
     *
     * @param  array<int, array<int, string>>  $segmen
     * @param  \Illuminate\Support\Collection<int, Role>  $semuaPeran
     */
    private function peranMemenuhi(string $namaPeran, array $segmen, ResourceMap $peta, $semuaPeran): bool
    {
        $role = $semuaPeran->firstWhere('name', $namaPeran);

        if ($role === null) {
            return false;
        }

        foreach ($segmen as $pilihan) {
            $lolos = false;

            foreach ($pilihan as $key) {
                $permission = $peta->permissionFor($key);

                if ($permission !== null && $role->permissions->contains('name', $permission)) {
                    $lolos = true;
                    break;
                }
            }

            if (! $lolos) {
                return false;
            }
        }

        return true;
    }

    /**
     * Key yang disebut di luar middleware rute: tampilan, controller, policy,
     * komponen, dan berkas navigasi.
     *
     * Dibaca dengan regex, bukan dieksekusi. Dua bentuk yang dicari:
     * `rk('x', ResourceAction::Y)` dan key harfiah `'x.y'` -- yang terakhir
     * dipakai uji dan beberapa pemeriksaan langsung. Salah baca ke arah
     * "dipakai" tidak berbahaya di sini: akibatnya cuma satu key tidak
     * dilaporkan menganggur.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function keyDiKode(): array
    {
        $pasti = [];
        $mungkin = [];
        $polaRk = "/rk\(\s*'([a-z0-9\-]+)'\s*,\s*ResourceAction::([A-Za-z]+)\s*\)/";
        $polaHarfiah = "/'([a-z][a-z0-9\-]*)\.(view|create|update|delete|approve|reject|print|export|assign|manage)'/";

        $berkas = collect([
            File::allFiles(resource_path('views')),
            File::allFiles(app_path()),
            File::allFiles(base_path('config')),
            File::allFiles(base_path('routes')),
        ])->flatten();

        foreach ($berkas as $satuBerkas) {
            if (! in_array($satuBerkas->getExtension(), ['php'], true)) {
                continue;
            }

            /*
             * Dua berkas dilewati, keduanya karena MENYEBUT key tanpa
             * memakainya:
             *
             * - ResourceKeys.php, daftar konstanta hasil generate. Ia
             *   menyebut seluruh key yang ada, jadi membacanya membuat setiap
             *   key tampak terpakai -- dan seluruh audit ini jadi diam.
             * - perintah ini sendiri, yang menyebut key sebagai contoh.
             */
            if (str_contains($satuBerkas->getPathname(), 'ResourceKeys.php')
                || str_contains($satuBerkas->getPathname(), 'AuditIzin.php')) {
                continue;
            }

            $isi = $satuBerkas->getContents();

            preg_match_all($polaRk, $isi, $cocok, PREG_SET_ORDER);
            foreach ($cocok as $satu) {
                $pasti[] = $satu[1].'.'.strtolower($satu[2]);
            }

            preg_match_all($polaHarfiah, $isi, $cocok, PREG_SET_ORDER);
            foreach ($cocok as $satu) {
                $mungkin[] = $satu[1].'.'.$satu[2];
            }
        }

        return [array_values(array_unique($pasti)), array_values(array_unique($mungkin))];
    }

    /** @param array<string, mixed> $temuan */
    private function keluarkanJson(array $temuan, array $pemilik): int
    {
        $this->line(json_encode(['temuan' => $temuan, 'pemilik' => $pemilik], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->adaMasalah($temuan) ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, mixed> $temuan */
    private function keluarkanTeks(array $temuan, array $pemilik, array $rute): int
    {
        $this->info(sprintf(
            '%d resource key, %d rute berpenjaga, %d peran (super-admin tidak dihitung).',
            count($pemilik), $rute['jumlah'], count(array_unique(array_merge(...array_values(array_map(
                fn (array $p) => $p, $pemilik
            ))))) ?: 0,
        ));

        $judul = [
            'key_tanpa_permission' => 'Key terdaftar tapi belum menunjuk permission',
            'key_tanpa_pemilik' => 'Key dipakai permukaan tapi TIDAK dimiliki peran mana pun',
            'key_tak_dikenal_di_rute' => 'Key dituntut rute tapi tidak ada di peta (selalu 403)',
            'key_tak_dikenal_di_tampilan' => 'Key disebut tampilan tapi tidak ada di peta (tombol tak pernah tergambar)',
            'key_tanpa_permukaan' => 'Key dimiliki peran tapi tidak dipakai rute maupun tampilan',
            'key_terdaftar_tak_terpakai' => 'Key terdaftar tapi tak dimiliki siapa pun dan tak dijaga apa pun',
            'pemilik_tanpa_jalan' => 'Peran memiliki key tapi tiap rute yang menuntutnya tertutup untuknya',
        ];

        foreach ($judul as $kunci => $teks) {
            $isi = $temuan[$kunci];

            $this->newLine();
            $this->line(($isi === [] ? '  OK   ' : '  ??   ').$teks.' — '.count($isi));

            foreach ($isi as $satu) {
                if (is_array($satu)) {
                    $this->line("         {$satu['peran']} memiliki {$satu['key']}; rute: ".implode(', ', array_unique($satu['rute'])));

                    continue;
                }

                $pemakai = array_slice(array_unique(array_column($rute['per_key'][$satu] ?? [], 'nama')), 0, 3);

                $this->line("         {$satu}".($pemakai === [] ? '  (hanya di tampilan)' : '  ← '.implode(', ', $pemakai)));
            }
        }

        return $this->adaMasalah($temuan) ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, mixed> $temuan */
    private function adaMasalah(array $temuan): bool
    {
        foreach (['key_tanpa_permission', 'key_tanpa_pemilik', 'key_tak_dikenal_di_rute', 'key_tak_dikenal_di_tampilan', 'pemilik_tanpa_jalan'] as $berat) {
            if ($temuan[$berat] !== []) {
                return true;
            }
        }

        return false;
    }
}
