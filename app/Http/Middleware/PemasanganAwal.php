<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membuka permukaan pemasangan node HANYA selama basis datanya masih kosong.
 *
 * # Masalah yang ditutupnya
 *
 * Seluruh akun panitia lahir di node global dan datang ke node gelanggang
 * lewat sinkron -- `users` bergolongan GLOBAL di PetaSinkron. Node gelanggang
 * yang baru dipasang karena itu tidak punya satu pun akun, sementara
 * satu-satunya jalan menarik data adalah tombol di halaman admin yang menuntut
 * login. Jalan keluarnya selama ini: membuat akun lokal lewat tinker, yang
 * pada penarikan pertama tertimpa akun bernomor sama dari node global.
 *
 * # Kenapa 404, bukan 403
 *
 * Node yang sudah terpasang tidak mengumumkan bahwa jalan ini pernah ada.
 * 403 memberi tahu penyusup bahwa alamatnya benar dan tinggal mencari cara
 * melewatinya; 404 tidak memberi tahu apa pun.
 *
 * # Tiga syarat, ketiganya harus terpenuhi
 *
 *   1. `users` kosong. Penarikan pertama membawa akun dari node global, dan
 *      sejak baris pertama itu masuk permukaan ini hilang dengan sendirinya --
 *      tanpa ada yang perlu ingat mematikannya.
 *   2. Token sinkron terpasang. Token kosong berarti sinkron memang mati di
 *      mesin ini, dan mesin yang belum dikonfigurasi tidak menyajikan apa pun
 *      ke jaringan gelanggang -- jaringan yang sama dengan perangkat penonton.
 *   3. Ada peer terdaftar. Tanpa peer tidak ada yang bisa ditarik, dan
 *      permukaan tanpa login sebaiknya tidak hidup sedetik lebih lama
 *      daripada gunanya.
 *
 * Yang bisa dilakukan orang asing di jaringan itu, seandainya ia menemukan
 * alamatnya: memicu penarikan dari peer yang sudah tertulis di `.env` mesin
 * ini sendiri, memakai token yang tertulis di situ juga. Ia tidak memilih
 * sumbernya, tidak menyisipkan apa pun, dan tidak ada yang bisa dibaca dari
 * basis data yang masih kosong.
 */
class PemasanganAwal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->pemasanganBelumSelesai(), 404);

        return $next($request);
    }

    private function pemasanganBelumSelesai(): bool
    {
        if ((string) config('sinkron.token') === '') {
            return false;
        }

        if (collect((array) config('sinkron.peer', []))->isEmpty()) {
            return false;
        }

        return User::query()->doesntExist();
    }
}
