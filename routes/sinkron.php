<?php

use App\Http\Controllers\Admin\SinkronController;
use Illuminate\Support\Facades\Route;

/*
 * Endpoint yang dipanggil LAPTOP GELANGGANG LAIN, bukan orang.
 *
 * Sengaja di luar grup 'web': yang mengetuk di sini tidak punya sesi, tidak
 * punya kuki, dan tidak bisa login. Pengamannya dua lapis dan keduanya
 * dipasang di bootstrap/app.php -- token bersama, dan pembatasan ke jaringan
 * lokal.
 *
 * Rute untuk menarik (tombol yang ditekan operator) TIDAK ada di sini. Ia
 * dipanggil orang yang sudah login, jadi tempatnya di routes/web.php bersama
 * halaman admin lain, dengan penjagaan izin yang sama.
 */

Route::get('/identitas', [SinkronController::class, 'identitas'])->name('identitas');
Route::get('/paket', [SinkronController::class, 'paket'])->name('paket');
