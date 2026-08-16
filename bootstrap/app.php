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
