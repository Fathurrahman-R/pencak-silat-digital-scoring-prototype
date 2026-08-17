<?php

use App\Http\Controllers\Public\LiveScoreController;
use Illuminate\Support\Facades\Route;

/*
 * Live score publik -- FR-H. Prefiks `gelanggang`/`turnamen` dipakai (bukan
 * langsung {arena}/{tournament} di akar) supaya kedua kelompok rute tidak
 * bisa saling tertukar oleh router: `/live/turnamen/9` tidak pernah bisa
 * dicoba dulu sebagai binding Arena bernomor "turnamen".
 */
Route::controller(LiveScoreController::class)->group(function () {
    Route::get('/gelanggang/{arena}', 'gelanggang')->name('gelanggang');
    Route::get('/gelanggang/{arena}/state', 'state')->name('gelanggang.state');

    Route::get('/turnamen/{tournament}', 'turnamen')->name('turnamen');
    Route::get('/turnamen/{tournament}/bagan/{weightClass}', 'bagan')->name('turnamen.bagan');
});
