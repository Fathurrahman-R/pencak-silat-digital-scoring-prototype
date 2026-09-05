<?php

namespace App\Http\Middleware;

use App\Support\Pemantauan\CatatWaktuTarikan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengukur berapa lama satu tarikan state berlangsung.
 *
 * Dipasang HANYA pada endpoint state panel, bukan global. Yang dicari adalah
 * gejala yang dirasakan operator di gelanggang, dan itu satu endpoint tertentu:
 * yang ditarik tiap panel terbuka, tiap ada siaran, ditambah sekali tiap dua
 * puluh detik selama babak berjalan. Mengukur seluruh permintaan akan
 * mencampurnya dengan halaman admin yang tidak ada hubungannya, dan
 * persentilnya berhenti berarti.
 *
 * Waktu dihitung SETELAH respons jadi, jadi ia mencakup seluruh kerja yang
 * membuat panel menunggu -- termasuk query yang justru sedang diselidiki.
 */
class CatatWaktuState
{
    public function __construct(private readonly CatatWaktuTarikan $catat) {}

    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);

        $respons = $next($request);

        $this->catat->catat((microtime(true) - $mulai) * 1000);

        return $respons;
    }
}
