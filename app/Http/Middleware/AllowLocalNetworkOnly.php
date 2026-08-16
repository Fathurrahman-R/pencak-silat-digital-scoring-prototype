<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Satu-satunya pengaman rute /overlay/* -- lihat catatan di routes/overlay.php.
 *
 * 403, bukan 404: ini bukan tentang menyembunyikan keberadaan rute dari
 * pemindai publik (itu tugas reverse proxy tunnel di Fase 5), melainkan
 * pesan yang jelas terbaca operator IT saat men-debug kenapa Browser Input
 * vMix-nya tidak mau menyala.
 */
class AllowLocalNetworkOnly
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        foreach (config('overlay.allowed_cidrs') as $cidr) {
            if (IpUtils::checkIp($ip, $cidr)) {
                return $next($request);
            }
        }

        throw new HttpException(403, 'Overlay hanya bisa diakses dari jaringan lokal gelanggang.');
    }
}
