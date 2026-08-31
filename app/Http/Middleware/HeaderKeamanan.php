<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk seluruh respons.
 *
 * Yang paling penting di sistem ini `X-Frame-Options`. Tanpa itu, panel juri
 * dan operator bisa dimuat di iframe tak terlihat pada halaman lain: juri
 * yang sedang login dikelabui menekan tombol nilai atau "Akhiri partai"
 * tanpa sadar. Aplikasi ini sendiri tidak memakai satu pun iframe, jadi
 * `DENY` tidak memutus apa pun -- termasuk overlay vMix, yang memuat
 * alamatnya langsung, bukan lewat bingkai.
 *
 * `X-Powered-By` dicabut karena mengumumkan versi PHP ke setiap pengunjung
 * tanpa memberi manfaat apa pun.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
