<?php

use App\Http\Controllers\OverlayController;
use App\Http\Controllers\Public\LiveScoreController;
use App\Http\Middleware\AllowLocalNetworkOnly;
use App\Http\Middleware\EnsureResourceAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\IngatTurnamenAktif;
use App\Http\Middleware\SiaranAktif;
use App\Http\Middleware\TokenSinkron;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        /*
         * Tanpa `commands:`. Berkas routes/console.php sebelumnya hanya berisi
         * command `inspire` bawaan Laravel dan tidak pernah dipakai; command
         * aplikasi sendiri tinggal di app/Console/Commands dan didaftarkan
         * otomatis. Tambahkan kembali baris ini kalau nanti ada command yang
         * memang perlu ditulis sebagai closure.
         */
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            /*
             * Dua endpoint JSON yang ditarik paling sering di seluruh sistem:
             * tiap halaman overlay vMix dan tiap penonton live score menarik
             * ulang begitu ada siaran, dan saat juri menekan beruntun itu
             * berarti belasan tarikan per detik.
             *
             * Keduanya sengaja TIDAK memakai grup 'web'. Yang dilepas bukan
             * pengamannya -- AllowLocalNetworkOnly dan throttle:live tetap
             * dipasang di bawah ini -- melainkan sesi. StartSession membaca
             * dan menulis satu baris sesi pada tiap permintaan (dua perjalanan
             * ke MySQL dengan SESSION_DRIVER=database), lalu EnsureUserIsActive
             * dan IngatTurnamenAktif ikut berjalan di belakangnya. Tidak satu
             * pun dari itu berguna untuk Web Browser Input vMix, yang tidak
             * bisa login, atau untuk penonton live score, yang tidak punya
             * turnamen aktif untuk diingat.
             *
             * HALAMANNYA tetap di grup 'web' bersama rute lain; hanya endpoint
             * JSON-nya yang dipindah ke sini, karena hanya itu yang ditarik
             * berulang-ulang.
             */
            // SubstituteBindings disebut sendiri karena ia biasanya datang
            // menumpang grup 'web'. Tanpa itu, {arena} tidak pernah berubah
            // jadi model -- controller menerima Arena kosong dan membalas
            // "tidak ada partai" untuk gelanggang yang sedang bertanding.
            // 'siaran' dipasang PALING DEPAN, sebelum SubstituteBindings:
            // itulah yang membuat balasan 503 saat siaran dimatikan tidak
            // pernah menyentuh database sama sekali. Urutan ini dijaga uji
            // yang menghitung query, jangan ditukar.
            Route::middleware(['siaran:overlay,json', SubstituteBindings::class, AllowLocalNetworkOnly::class])
                ->get('overlay/state/{arena}', [OverlayController::class, 'state'])
                ->name('overlay.state');

            Route::middleware(['siaran:live,json', SubstituteBindings::class, 'throttle:live'])
                ->get('live/gelanggang/{arena}/state', [LiveScoreController::class, 'state'])
                ->name('live.gelanggang.state');

            /*
             * Overlay siaran vMix: bukan API terpisah dan bukan bagian dari
             * routes/web.php, karena satu-satunya pengamannya adalah
             * AllowLocalNetworkOnly -- Web Browser Input vMix tidak bisa
             * login, jadi rute ini sengaja tidak pernah melewati middleware
             * 'auth' sama sekali.
             */
            Route::middleware(['web', AllowLocalNetworkOnly::class])
                ->prefix('overlay')
                ->name('overlay.')
                ->group(base_path('routes/overlay.php'));

            /*
             * Live score publik: satu-satunya kelompok rute yang memang
             * dirancang untuk diteruskan tunnel ke internet (lihat Fase 5 di
             * docs/RENCANA.md untuk konfigurasi reverse proxy-nya). Dibatasi
             * `throttle:live`, BUKAN AllowLocalNetworkOnly -- justru
             * kebalikan dari overlay, rute ini harus bisa dijangkau dari
             * luar jaringan gelanggang.
             */
            Route::middleware(['web', 'throttle:live'])
                ->prefix('live')
                ->name('live.')
                ->group(base_path('routes/live.php'));

            /*
             * Sinkron antar laptop gelanggang: kelompok rute ketiga yang
             * sengaja di luar grup 'web'.
             *
             * Yang mengetuk di sini bukan orang melainkan laptop gelanggang
             * lain -- ia tidak punya sesi, tidak punya kuki, dan tidak bisa
             * login. Melewatkan StartSession berarti tiap penarikan tidak
             * menulis baris sesi yang tidak akan pernah dibaca siapa pun.
             *
             * Dua pengaman menggantikannya, dan keduanya harus ada:
             * TokenSinkron (token bersama yang disalin panitia antar mesin,
             * dan yang membuat endpoint ini MATI kalau belum diisi) serta
             * AllowLocalNetworkOnly. Data kejuaraan lengkap mengalir lewat
             * sini; ia tidak boleh bisa dijangkau dari luar LAN gelanggang
             * dalam keadaan apa pun.
             */
            Route::middleware([SubstituteBindings::class, AllowLocalNetworkOnly::class, TokenSinkron::class])
                ->prefix('sinkron')
                ->name('sinkron.')
                ->group(base_path('routes/sinkron.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resource' => EnsureResourceAccess::class,
            'active' => EnsureUserIsActive::class,
            'siaran' => SiaranAktif::class,
        ]);

        /*
         * Proxy tunnel yang berdiri di depan aplikasi -- dan HANYA itu.
         *
         * Live score publik disajikan lewat reverse proxy ber-TLS
         * (docs/TUNNELING.md): penonton membuka `https://live.domain`, proxy
         * meneruskannya ke Laravel sebagai `http://localhost:8000`. Tanpa
         * baris ini Laravel tidak pernah tahu permintaannya datang lewat TLS,
         * dan SELURUH alamat yang dibuatnya berskema `http` -- termasuk
         * `<script src>` dan `<link rel=stylesheet>` ke /build/*.
         *
         * Peramban memblokir aset `http` di halaman `https` sebagai konten
         * campuran. Yang tampil bukan halaman yang jelek, melainkan halaman
         * tanpa CSS dan tanpa JS sama sekali: tidak ada Alpine, tidak ada
         * Echo, tidak ada angka yang bergerak. Terbaca sebagai "siarannya
         * mati", padahal berkasnya tidak pernah sampai. Safari iOS
         * memblokirnya tanpa satu pun pesan yang terlihat.
         *
         * Dipercaya HANYA loopback, bukan `*` dan bukan seluruh RFC 1918.
         *
         * Proxy-nya memang berdiri di mesin yang sama --
         * `reverse_proxy localhost:8000` di docs/TUNNELING.md -- jadi loopback
         * sudah cukup. Melebarkannya ke seluruh LAN berarti perangkat mana pun
         * di WiFi venue boleh mengarang alamat asalnya sendiri lewat
         * X-Forwarded-For, dan alamat itulah yang dibaca AllowLocalNetworkOnly
         * untuk menjaga overlay vMix dan endpoint sinkron.
         *
         * Kalau suatu saat proxy-nya dipindah ke mesin lain, tambahkan alamat
         * mesin ITU di sini -- satu alamat, bukan satu rentang.
         */
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        /*
         * Global, bukan hanya grup web: halaman galat, overlay vMix, dan
         * live score publik sama-sama perlu dijaga dari pembingkaian.
         */
        $middleware->append(HeaderKeamanan::class);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            IngatTurnamenAktif::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        /*
         * Penjadwal ini hanya berjalan kalau `php artisan schedule:work`
         * dijalankan, dan di mesin gelanggang saat hari-H ia TIDAK dijalankan
         * (lihat docs/INSTALASI-LAN.md). Isinya sengaja hal-hal yang boleh
         * tertunda sampai ada yang menjalankannya: tidak ada satu pun di sini
         * yang menjadi syarat pertandingan berjalan.
         */
        $schedule->command('silat:arsip --dorong')->everyTenMinutes()->withoutOverlapping();

        /*
         * Jaring pengaman, bukan jalur utama: arsip sudah didorong saat partai
         * disahkan. Yang disapu di sini cuma yang gagal karena node global
         * kebetulan mati waktu itu.
         */
        $schedule->command('model:prune')->daily();
        $schedule->command('queue:prune-failed')->daily();
        $schedule->command('cache:prune-stale-tags')->hourly();

        /*
         * Pemangkasan riwayat juri TIDAK dijadwalkan, dan itu disengaja.
         * Ia menghapus bukti. Yang menekan tombolnya harus manusia yang tahu
         * kejuaraannya sedang di titik mana -- bukan penjadwal yang berjalan
         * pukul tiga pagi.
         */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Id yang tidak ada dijawab dengan kalimat sendiri, bukan pesan bawaan
         * Laravel.
         *
         * Bawaannya berbunyi "No query results for model [App\Models\WeightClass]
         * 999999": berbahasa Inggris di aplikasi yang seluruh pesannya Indonesia,
         * dan menyebut nama kelas beserta ruang namanya kepada siapa pun yang
         * mengetuk. Nama tabel dan struktur internal tidak menolong orang yang
         * salah menekan tautan, dan menolong orang yang sedang meraba-raba
         * bentuk sistem ini dari luar.
         *
         * Yang ditangkap NotFoundHttpException, bukan ModelNotFoundException:
         * Laravel sudah membungkus yang kedua jadi yang pertama sebelum callback
         * ini dipanggil, dan pesan aslinya ikut terbawa di dalamnya. Hanya pesan
         * berpola bawaan itu yang ditimpa -- `abort(404, '...')` yang ditulis
         * sendiri di controller tetap sampai apa adanya.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! str_starts_with($e->getMessage(), 'No query results for model')) {
                return null;
            }

            $pesan = 'Data yang diminta tidak ditemukan. Kemungkinan sudah dihapus, '
                .'atau tautannya menunjuk kejuaraan lain.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $pesan], 404);
            }

            return response()->view('errors.404', ['message' => $pesan], 404);
        });
    })->create();
