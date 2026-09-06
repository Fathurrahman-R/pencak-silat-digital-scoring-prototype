<?php

use App\Http\Controllers\Public\LiveScoreController;
use Illuminate\Support\Facades\Route;

/*
 * Live score publik -- FR-H. Prefiks `gelanggang`/`turnamen` dipakai (bukan
 * langsung {arena}/{tournament} di akar) supaya kedua kelompok rute tidak
 * bisa saling tertukar oleh router: `/live/turnamen/9` tidak pernah bisa
 * dicoba dulu sebagai binding Arena bernomor "turnamen".
 */
/*
 * `live.gelanggang.state` TIDAK ada di sini. Ia didaftarkan sendiri di
 * bootstrap/app.php tanpa grup 'web' -- tiap penonton menariknya ulang pada
 * tiap siaran, dan sesi yang tidak pernah dipakai penonton anonim membebani
 * setiap tarikannya. Batas throttle:live tetap terpasang di sana.
 */
Route::controller(LiveScoreController::class)->group(function () {
    // Satu-satunya halaman live score yang memakai Echo, jadi satu-satunya
    // yang ikut mati bersama LIVE_SCORE_ENABLED.
    Route::get('/gelanggang/{arena}', 'gelanggang')->middleware('siaran:live')->name('gelanggang');

    /*
     * Tiga halaman berikut TIDAK ikut saklar: tidak satu pun memakai Echo,
     * jadi tidak ada beban Reverb yang bisa dihemat dengan mematikannya --
     * sementara penonton tetap butuh melihat hasil, medali, dan bagan.
     */
    Route::get('/turnamen/{tournament}', 'turnamen')->name('turnamen');
    Route::get('/turnamen/{tournament}/medali', 'medali')->name('turnamen.medali');
    Route::get('/turnamen/{tournament}/bagan/{weightClass}', 'bagan')->name('turnamen.bagan');
});
