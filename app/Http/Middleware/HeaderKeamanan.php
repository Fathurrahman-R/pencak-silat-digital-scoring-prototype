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

        /*
         * Halaman milik pengguna yang login tidak boleh tertinggal di
         * back/forward cache peramban.
         *
         * Terbukti di lapangan: logout dari panel juri, tekan tombol back, dan
         * panelnya muncul kembali lengkap dengan nama kedua atlet. Yang tampil
         * salinan beku, bukan data hidup -- tapi HP juri berpindah tangan
         * sepanjang hari, dan halaman itu tidak seharusnya masih ada di sana.
         *
         * `no-cache` saja tidak cukup: bfcache tidak diatur olehnya, hanya
         * `no-store` yang melarang peramban menyimpannya. Balasan tanpa sesi
         * -- overlay vMix dan live score penonton, yang justru ingin di-cache
         * -- tidak disentuh.
         */
        if ($request->hasSession() && $request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
