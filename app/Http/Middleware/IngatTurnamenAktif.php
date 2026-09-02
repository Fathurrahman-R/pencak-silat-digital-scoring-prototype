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
 *
 * TIDAK semua halaman ber-{tournament} berarti "saya sedang bekerja di
 * kejuaraan ini". Sebagian hanya mengurus CATATANNYA dari daftar kejuaraan:
 * menekan "Ubah" di satu baris, atau mengklik baris untuk mengintip panelnya.
 * Selama keduanya ikut mengganti, kejuaraan aktif berpindah setiap kali daftar
 * kejuaraan disentuh, dan seluruh sidebar berganti isi tanpa diminta.
 * Perpindahannya harus disengaja, lewat tombol "Buka".
 */
class IngatTurnamenAktif
{
    public const KUNCI = 'turnamen_aktif';

    /**
     * Halaman yang mengurus catatan kejuaraan, bukan isinya.
     *
     * Didaftar satu per satu, bukan diterka dari pola nama: `panel` dan
     * `status` sama-sama bernama satu tingkat seperti `edit`, sementara
     * `rekap.index` dua tingkat -- pola apa pun akan salah menebak salah
     * satunya.
     *
     * @var array<int, string>
     */
    public const BUKAN_PEMBUKA = [
        'admin.turnamen.panel',
        'admin.turnamen.edit',
        'admin.turnamen.update',
        'admin.turnamen.status',
        'admin.turnamen.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $request->route('tournament');

        if ($tournament instanceof Tournament && ! $this->hanyaMengurusCatatan($request)) {
            $request->session()->put(self::KUNCI, $tournament->id);
        }

        return $next($request);
    }

    private function hanyaMengurusCatatan(Request $request): bool
    {
        return in_array($request->route()?->getName(), self::BUKAN_PEMBUKA, true);
    }
}
