<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga endpoint sinkron dengan token bersama.
 *
 * # Kenapa token, bukan sesi login
 *
 * Yang menarik data di sini bukan orang, melainkan laptop gelanggang lain.
 * Ia tidak punya sesi, tidak punya kuki, dan tidak boleh dipaksa
 * mengarangnya. Token bersama yang disalin panitia antar mesin adalah
 * bentuk paling sederhana yang masih menjawab pertanyaan "apakah yang
 * mengetuk ini memang salah satu dari kita".
 *
 * # Kenapa token kosong berarti MATI, bukan terbuka
 *
 * Pemasangan yang belum dikonfigurasi tidak punya token. Kalau kosong
 * diartikan "tidak perlu diperiksa", laptop yang baru dipasang akan
 * menyajikan seluruh isi basis datanya ke siapa pun di LAN gelanggang --
 * jaringan yang juga dipakai perangkat penonton dan panitia. Kosong berarti
 * endpoint ini tidak hidup sama sekali.
 *
 * # Kenapa dibandingkan dengan hash_equals
 *
 * Perbandingan string biasa berhenti di karakter pertama yang berbeda, dan
 * lama perbandingannya membocorkan berapa banyak karakter awal yang sudah
 * benar. Di LAN yang sama, itu cukup untuk menebak token satu karakter demi
 * satu.
 */
class TokenSinkron
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('sinkron.token');

        if ($token === '') {
            abort(404);
        }

        $dibawa = (string) ($request->header('X-Sinkron-Token') ?? $request->query('token', ''));

        abort_unless($dibawa !== '' && hash_equals($token, $dibawa), 403, 'Token sinkron tidak cocok.');

        return $next($request);
    }
}
