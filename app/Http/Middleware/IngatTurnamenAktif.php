<?php

namespace App\Http\Middleware;

use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengingat kejuaraan yang sedang dibuka.
 *
 * Hampir seluruh pekerjaan panitia berlangsung di dalam satu kejuaraan, dan
 * hampir seluruh halamannya bersarang di bawahnya. Tanpa ingatan ini, sidebar
 * tidak punya cara menampilkan bagian-bagian kejuaraan sebagai menu — panitia
 * harus kembali ke daftar kejuaraan setiap kali berpindah bagian.
 *
 * Yang disimpan hanya nomornya. Sidebar mengambil datanya sendiri, sehingga
 * kejuaraan yang dihapus atau berganti nama tidak meninggalkan salinan basi di
 * sesi.
 */
class IngatTurnamenAktif
{
    public const KUNCI = 'turnamen_aktif';

    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $request->route('tournament');

        if ($tournament instanceof Tournament) {
            $request->session()->put(self::KUNCI, $tournament->id);
        }

        return $next($request);
    }
}
