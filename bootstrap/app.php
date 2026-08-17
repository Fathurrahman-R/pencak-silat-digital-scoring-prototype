<?php

use App\Http\Middleware\AllowLocalNetworkOnly;
use App\Http\Middleware\EnsureResourceAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IngatTurnamenAktif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
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
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resource' => EnsureResourceAccess::class,
            'active' => EnsureUserIsActive::class,
        ]);

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
