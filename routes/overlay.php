<?php

use App\Http\Controllers\OverlayController;
use Illuminate\Support\Facades\Route;

/*
 * Semua rute di sini sudah berada di balik prefix 'overlay' dan middleware
 * AllowLocalNetworkOnly -- didaftarkan lewat blok then() di bootstrap/app.php,
 * bukan routes/web.php. Tidak satu pun boleh diberi middleware 'auth' atau
 * 'resource:...': Web Browser Input vMix tidak bisa login.
 */
/*
 * `overlay.state` TIDAK ada di sini. Ia didaftarkan sendiri di bootstrap/app.php
 * tanpa grup 'web' -- endpoint itu ditarik ulang oleh tiap halaman overlay pada
 * tiap siaran, dan sesi yang tidak pernah dipakai vMix membebani setiap
 * tarikannya. Alasan lengkapnya ada di komentar blok then() di berkas itu.
 */
Route::controller(OverlayController::class)->group(function () {
    /*
     * Empat halaman yang benar-benar realtime. Saat OVERLAY_ENABLED mati,
     * rutenya TETAP terdaftar -- panel admin siaran mencetak URL-nya, dan
     * URL yang hilang di sana lebih membingungkan operator daripada halaman
     * yang menjelaskan dirinya sendiri. Yang bertukar hanya isinya.
     */
    Route::middleware('siaran:overlay')->group(function () {
        Route::get('/scorebug/{arena}', 'scorebug')->name('scorebug');
        Route::get('/athlete/{arena}/{corner}', 'athlete')->name('athlete');
        Route::get('/breakdown/{arena}', 'breakdown')->name('breakdown');
        Route::get('/result/{arena}', 'result')->name('result');
    });

    /*
     * Bagan sengaja DI LUAR saklar: ia tidak memakai Echo sama sekali --
     * halaman statis yang disegarkan manual -- jadi ia tidak menyumbang satu
     * pun muatan ke Reverb. Mematikannya tidak menghemat apa pun sambil
     * menghilangkan grafis yang sering dipasang sepanjang acara.
     */
    Route::get('/bracket/{tournament}', 'bracket')->name('bracket');
});
