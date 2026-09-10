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
    /** @var array<string, array<int, string>> key => nama tampilan yang menyebutnya */
    private array $asalKey = [];

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
            'penjaga_tak_sejalan' => $this->penjagaTakSejalan($rute),
            'pemilik_tanpa_layar' => [],
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

        /*
         * 5. Pemilik tanpa layar.
         *
         * Key yang hanya hidup di dalam tampilan -- tombol atau blok yang
         * dibungkus @resource -- tidak dijaga rute mana pun, jadi pemeriksaan
         * "pemilik tanpa jalan" di atas tidak pernah menyentuhnya. Yang
         * menentukan keterjangkauannya HALAMAN yang memuatnya.
         *
         * Persis di sini letak cacat Sekretariat: ia memegang
         * `nomor-jurus.update`, tombolnya ada di `admin.jurus.daftar`, dan
         * halaman itu dijaga key yang tidak ia punya.
         */
        $tampilanRute = $this->tampilanPerRute();

        foreach ($this->asalKey as $key => $tampilan) {
            /*
             * Key yang PUNYA rute sendiri tetap diperiksa di sini.
             *
             * Percobaan pertama melewatinya, dan itu justru membutakan
             * pemeriksaan ini terhadap cacat yang melahirkannya:
             * `nomor-jurus.update` memang menjaga rute POST-nya sendiri, dan
             * Sekretariat memang boleh menembak rute itu. Yang tidak bisa ia
             * lakukan adalah MELIHAT formulirnya -- halaman yang memuatnya
             * dijaga key lain. Yang menentukan bukan pemilik rutenya,
             * melainkan pemilik halamannya.
             */
            foreach ($pemilik[$key] ?? [] as $namaPeran) {
                $bisa = false;
                $halaman = [];

                /*
                 * Rute GET yang menuntut key ini sendiri sudah cukup: peran
                 * yang boleh MEMBUKA sesuatu dengan key itu jelas bisa
                 * memakainya. Rute POST tidak dihitung -- bisa menembak
                 * endpoint bukan berarti bisa melihat formulirnya, dan itulah
                 * seluruh isi cacat yang melahirkan pemeriksaan ini.
                 */
                foreach ($rute['per_key'][$key] ?? [] as $satuRute) {
                    if ($satuRute['get'] && $this->peranMemenuhi($namaPeran, $satuRute['keys'], $peta, $peran)) {
                        $bisa = true;
                        break;
                    }
                }

                if ($bisa) {
                    continue;
                }

                foreach (array_unique($tampilan) as $satuTampilan) {
                    foreach ($tampilanRute[$satuTampilan] ?? [] as $satuRute) {
                        $halaman[] = $satuRute['nama'];

                        if ($this->peranMemenuhi($namaPeran, $satuRute['keys'], $peta, $peran)) {
                            $bisa = true;
                            break 2;
                        }
                    }
                }

                if (! $bisa && $halaman !== []) {
                    $temuan['pemilik_tanpa_layar'][] = [
                        'key' => $key,
                        'peran' => $namaPeran,
                        'rute' => array_unique($halaman),
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

            $bisaDibuka = in_array('GET', $satu->methods(), true);

            foreach ($segmen as $satuSegmen) {
                foreach ($satuSegmen as $key) {
                    $perKey[$key][] = ['nama' => $nama, 'keys' => $segmen, 'get' => $bisaDibuka];
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
        $this->asalKey = [];
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
                $key = $satu[1].'.'.strtolower($satu[2]);
                $pasti[] = $key;

                if (str_starts_with($satuBerkas->getPathname(), resource_path('views'))) {
                    $this->asalKey[$key][] = $this->namaTampilan($satuBerkas->getPathname());
                }
            }

            preg_match_all($polaHarfiah, $isi, $cocok, PREG_SET_ORDER);
            foreach ($cocok as $satu) {
                $mungkin[] = $satu[1].'.'.$satu[2];
            }
        }

        return [array_values(array_unique($pasti)), array_values(array_unique($mungkin))];
    }

    /** `resources/views/admin/jurus/daftar.blade.php` -> `admin.jurus.daftar` */
    private function namaTampilan(string $jalur): string
    {
        $relatif = str_replace([resource_path('views').DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $jalur);

        return str_replace('/', '.', preg_replace('/\.blade\.php$/', '', $relatif));
    }

    /**
     * Tampilan yang dirender tiap rute, dibaca dari SUMBER method controller.
     *
     * Statis dan kasar -- `view($nama)` dengan variabel tidak terbaca -- tapi
     * cukup untuk pertanyaan yang penting: halaman mana yang memuat tombol
     * ini, dan siapa boleh membukanya.
     *
     * @return array<string, array<int, array{nama: string, keys: array<int, array<int, string>>}>>
     */
    private function tampilanPerRute(): array
    {
        $peta = [];

        foreach (Route::getRoutes() as $satu) {
            /** @var RouteObjek $satu */
            $aksi = $satu->getActionName();

            if (! str_contains($aksi, '@')) {
                continue;
            }

            [$kelas, $method] = explode('@', $aksi);

            if (! class_exists($kelas) || ! method_exists($kelas, $method)) {
                continue;
            }

            try {
                $refleksi = new \ReflectionMethod($kelas, $method);
            } catch (\Throwable) {
                continue;
            }

            $berkas = $refleksi->getFileName();

            if ($berkas === false) {
                continue;
            }

            $sumber = implode('', array_slice(
                file($berkas),
                $refleksi->getStartLine() - 1,
                $refleksi->getEndLine() - $refleksi->getStartLine() + 1,
            ));

            preg_match_all("/view\(\s*'([a-z0-9_.\-]+)'/i", $sumber, $cocok, PREG_SET_ORDER);

            $segmen = [];

            foreach ($satu->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'resource:')) {
                    foreach (explode(',', substr($middleware, strlen('resource:'))) as $bagian) {
                        $segmen[] = array_values(array_filter(array_map('trim', explode('|', $bagian))));
                    }
                }
            }

            foreach ($cocok as $satuCocok) {
                $peta[$satuCocok[1]][] = ['nama' => $satu->getName() ?? $satu->uri(), 'keys' => $segmen];
            }
        }

        return $peta;
    }

    /**
     * Rute yang kata kerjanya tidak sejalan dengan aksi key penjaganya.
     *
     * `tarif.destroy` yang dijaga `tarif.update` berarti siapa pun yang boleh
     * menyunting tarif juga boleh menghapusnya, dan `tarif.delete` yang
     * diberikan seseorang tidak berlaku apa-apa. Tidak semuanya salah --
     * membatalkan pengurangan memang sengaja dijaga `hasil-jurus.update`,
     * bukan `.delete` -- jadi daftar ini untuk dinilai, bukan untuk dipatuhi.
     *
     * @param  array{per_key: array<string, array<int, array{nama: string, keys: array<int, array<int, string>>}>>, jumlah: int}  $rute
     * @return array<int, string>
     */
    private function penjagaTakSejalan(array $rute): array
    {
        $harapan = [
            'destroy' => 'delete',
            'store' => 'create',
            'export' => 'export',
            'cetak' => 'print',
        ];

        $temuan = [];

        foreach ($rute['per_key'] as $key => $daftar) {
            foreach ($daftar as $satu) {
                $akhiran = last(explode('.', $satu['nama']));

                if (! isset($harapan[$akhiran])) {
                    continue;
                }

                $aksiDituntut = array_map(
                    fn (array $pilihan) => array_map(fn (string $k) => last(explode('.', $k)), $pilihan),
                    $satu['keys'],
                );

                $punya = in_array($harapan[$akhiran], array_merge(...$aksiDituntut), true);

                if (! $punya) {
                    $temuan[] = "{$satu['nama']} dijaga ".implode(' + ', array_map(
                        fn (array $pilihan) => implode('|', $pilihan), $satu['keys'],
                    ))." — diharap aksi `{$harapan[$akhiran]}`";
                }
            }
        }

        return array_values(array_unique($temuan));
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
            'pemilik_tanpa_layar' => 'Peran memiliki key yang tombolnya ada di halaman yang tertutup untuknya',
            'penjaga_tak_sejalan' => 'Kata kerja rute tidak sejalan dengan aksi key penjaganya (perlu dinilai manusia)',
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

                // Temuan yang sudah berupa kalimat (kata kerja tak sejalan)
                // tidak diberi keterangan pemakai: ia menyebut rutenya sendiri.
                if (str_contains($satu, ' ')) {
                    $this->line("         {$satu}");

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
        foreach (['key_tanpa_permission', 'key_tanpa_pemilik', 'key_tak_dikenal_di_rute', 'key_tak_dikenal_di_tampilan', 'pemilik_tanpa_jalan', 'pemilik_tanpa_layar'] as $berat) {
            if ($temuan[$berat] !== []) {
                return true;
            }
        }

        return false;
    }
}
