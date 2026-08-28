<?php

namespace App\Http\Controllers\Public;

use App\Enums\StatusTurnamen;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;

/**
 * Halaman depan publik.
 *
 * Sebelumnya alamat ini merender halaman jualan bawaan boilerplate: judul
 * "Hak akses yang berubah lewat panel, bukan lewat deploy", penjelasan RBAC
 * resource key, tabel harga tiga tingkat (Rp 0 / Rp 490rb / Hubungi kami), dan
 * footer "boilerplate Laravel". Tidak ada satu kata pun tentang pencak silat.
 *
 * Padahal inilah alamat pertama yang dibuka penonton dan official kontingen
 * lewat tunnel. Yang mereka cari cuma dua: kejuaraan mana yang sedang berjalan,
 * dan di mana melihat skornya.
 */
class BerandaController extends Controller
{
    public function __invoke(): View
    {
        $kejuaraan = Tournament::query()
            ->whereIn('status', [StatusTurnamen::Berjalan, StatusTurnamen::Draf])
            ->withCount('arenas')
            ->with('arenas:id,tournament_id,name')
            /*
             * Yang sedang berjalan selalu di atas, apa pun tanggalnya -- itu
             * yang dicari orang saat membuka halaman ini di tengah acara.
             */
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StatusTurnamen::Berjalan->value])
            ->orderBy('starts_on')
            ->take(6)
            ->get();

        return view('welcome', ['kejuaraan' => $kejuaraan]);
    }
}
