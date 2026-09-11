<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 *   1. Penarikan awal belum tuntas: masih ada peer yang belum pernah ditarik
 *      sampai habis, ATAU belum ada akun yang memegang
 *      `sinkron-gelanggang.view` untuk meneruskannya sendiri.
 *
 *      Syaratnya dulu "users masih kosong", dan itu menutup halaman ini
 *      terlalu dini: penarikan awal butuh belasan potongan, sementara akun
 *      tiba di potongan pertama dan PERANNYA menyusul beberapa potongan
 *      kemudian. Node lalu berdiri di jalan buntu -- halaman pemasangan sudah
 *      404, sementara halaman sinkron menuntut izin yang belum tiba, dan tidak
 *      ada seorang pun yang bisa masuk untuk melanjutkan. Terlihat begitu di
 *      peramban, 11 September 2026: 26 akun masuk, nol peran, penarikan
 *      berhenti di seperempat jalan.
 *
 *      Ukuran "sudah ada akun berizin" pun ternyata belum cukup: penyemaian
 *      menaruh akun dan perannya di potongan PERTAMA, jadi halaman ini
 *      menutup diri setelah seratus milidetik sementara dua belas ribu baris
 *      sisanya belum berangkat. Akun ada, kejuaraannya tidak, dan tidak ada
 *      lagi permukaan untuk melanjutkan.
 *
 *      Ukuran yang benar: penarikan awalnya sudah sampai ujung.
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

        /*
         * Bukan `users` kosong, melainkan: belum ada yang memegang kunci
         * halaman sinkron. Selama belum ada, halaman ini satu-satunya jalan
         * menyelesaikan penarikan, dan menutupnya berarti mengunci mesin itu
         * dari luar.
         */
        if ($this->adaPeerYangBelumTuntas()) {
            return true;
        }

        return ! $this->adaYangBisaMenarik();
    }

    /**
     * Apakah masih ada peer yang belum pernah ditarik sampai habis.
     *
     * Peer yang belum punya baris kursor sama sekali dihitung belum tuntas --
     * itu keadaan mesin yang belum menarik apa pun.
     */
    private function adaPeerYangBelumTuntas(): bool
    {
        $peer = collect((array) config('sinkron.peer', []))->pluck('nama');

        $tuntas = DB::table('sinkron_kursor')
            ->whereNotNull('selesai_pada')
            ->pluck('peer');

        return $peer->diff($tuntas)->isNotEmpty();
    }

    /**
     * Apakah sudah ada akun yang bisa membuka halaman Sinkron Gelanggang.
     *
     * Dibaca lewat izin, bukan lewat nama peran: peran melebur dan berganti
     * nama antar versi, sementara kunci halamannya tetap sama.
     */
    private function adaYangBisaMenarik(): bool
    {
        if (User::query()->doesntExist()) {
            return false;
        }

        return User::query()->get()->contains(
            fn (User $pengguna) => $pengguna->can(rk('sinkron-gelanggang', \App\Enums\ResourceAction::View)),
        );
    }
}
