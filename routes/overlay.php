<?php

use App\Http\Controllers\OverlayController;
use Illuminate\Support\Facades\Route;

/*
 * Semua rute di sini sudah berada di balik prefix 'overlay' dan middleware
 * AllowLocalNetworkOnly -- didaftarkan lewat blok then() di bootstrap/app.php,
 * bukan routes/web.php. Tidak satu pun boleh diberi middleware 'auth' atau
 * 'resource:...': Web Browser Input vMix tidak bisa login.
 */
Route::controller(OverlayController::class)->group(function () {
    Route::get('/state/{arena}', 'state')->name('state');

    Route::get('/scorebug/{arena}', 'scorebug')->name('scorebug');
    Route::get('/athlete/{arena}/{corner}', 'athlete')->name('athlete');
    Route::get('/breakdown/{arena}', 'breakdown')->name('breakdown');
    Route::get('/result/{arena}', 'result')->name('result');
    Route::get('/bracket/{tournament}', 'bracket')->name('bracket');
});
