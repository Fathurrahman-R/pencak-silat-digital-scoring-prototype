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
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

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
         * Global, bukan hanya grup web: halaman galat, overlay vMix, dan
         * live score publik sama-sama perlu dijaga dari pembingkaian.
         */
        $middleware->append(HeaderKeamanan::class);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            IngatTurnamenAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
