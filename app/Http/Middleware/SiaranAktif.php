<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga saklar siaran: OVERLAY_ENABLED dan LIVE_SCORE_ENABLED.
 *
 * Dipasang sebagai middleware, bukan sebagai percabangan `if` saat
 * mendaftarkan rute seperti halaman peraga di routes/web.php. Preseden itu
 * cocok untuk "rute hilang total"; yang dibutuhkan di sini justru sebaliknya
 * -- rutenya tetap terdaftar, hanya perilakunya yang bertukar, supaya
 * `route('overlay.scorebug', ...)` di panel admin dan di beranda tidak pecah
 * saat siarannya dimatikan. Rute yang dicabang saat boot juga praktis
 * mustahil diuji dalam keadaan mati tanpa membangun rangkaian uji kedua.
 *
 * Dua bentuk balasan, dipilih lewat parameter kedua:
 *
 *   - `halaman` (bawaan) -- merender halaman yang menjelaskan dirinya
 *     sendiri, dengan status 200. Bukan 503: halaman ini adalah jawaban yang
 *     benar untuk keadaan tersebut, bukan kegagalan. Web Browser Input vMix
 *     menyalakan halaman dan meninggalkannya berjam-jam; status galat di situ
 *     hanya menghasilkan percobaan ulang yang tidak menolong siapa pun.
 *
 *   - `json` -- 503 untuk kedua endpoint state. Di situ 503 memang tepat:
 *     yang meminta adalah mesin, bukan orang, dan "sedang tidak dilayani"
 *     adalah keterangan yang bisa dibaca monitoring maupun klien lama.
 *
 * Middleware ini dipasang PALING DEPAN pada kedua endpoint state, sebelum
 * SubstituteBindings, supaya balasan 503 tidak pernah menyentuh database.
 */
class SiaranAktif
{
    public function handle(Request $request, Closure $next, string $permukaan, string $balasan = 'halaman'): Response
    {
        if (config("{$this->kunci($permukaan)}.enabled")) {
            return $next($request);
        }

        if ($balasan === 'json') {
            return response()->json([
                'aktif' => false,
                'pesan' => 'Siaran sedang dimatikan di server ini.',
            ], 503);
        }

        return response()->view('siaran.nonaktif', $this->keterangan($permukaan));
    }

    private function kunci(string $permukaan): string
    {
        return match ($permukaan) {
            'overlay' => 'overlay',
            'live' => 'live',
        };
    }

    /**
     * Petunjuk teknis hanya diberikan pada permukaan overlay. Overlay dibatasi
     * jaringan lokal gelanggang, jadi yang membacanya operator IT yang berdiri
     * di depan vMix -- ia persis butuh nama kunci .env itu. Live score bisa
     * diteruskan tunnel ke internet, dan nama kunci konfigurasi server bukan
     * sesuatu yang perlu dibaca penonton.
     *
     * @return array<string, string|null>
     */
    private function keterangan(string $permukaan): array
    {
        return $permukaan === 'overlay'
            ? [
                'judul' => 'Overlay siaran dimatikan',
                'pesan' => 'Server ini sedang berjalan tanpa overlay siaran, '
                    .'jadi grafis vMix tidak menerima pembaruan.',
                /*
                 * `optimize:clear`, bukan `config:clear`. Di mode hari-H rute
                 * dan tampilan ikut di-cache, dan membersihkan konfigurasi
                 * saja meninggalkan cache lain yang basi -- persis perintah
                 * yang dilarang docs/SIMULASI-LAPANGAN.md.
                 */
                'petunjuk' => 'Nyalakan dengan menyetel OVERLAY_ENABLED=true di berkas .env, '
                    .'lalu jalankan php artisan optimize:clear.',
            ]
            : [
                'judul' => 'Live score sedang tidak ditayangkan',
                'pesan' => 'Panitia belum menayangkan skor langsung untuk gelanggang ini. '
                    .'Hasil pertandingan, perolehan medali, dan bagan tetap bisa dibuka.',
                'petunjuk' => null,
            ];
    }
}
