<?php

namespace App\Http\Controllers;

use App\Support\Sinkron\PenarikPeer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penarikan pertama sebuah node, sebelum ada satu pun akun untuk login.
 *
 * Berdiri terpisah dari SinkronController, bukan menumpang di sana, karena
 * yang berbeda bukan cuma penjagaannya melainkan seluruh keadaannya: halaman
 * ini dirender tanpa pengguna, jadi ia tidak boleh memakai tata letak admin
 * yang menyusun menu dan sidebar dari peran orang yang sedang login. Satu
 * halaman polos, satu tombol, dan sesudah itu ia menghilang sendiri --
 * PemasanganAwal menutupnya begitu akun pertama masuk.
 *
 * Isinya sengaja tidak menampilkan kesehatan gelanggang, kursor, maupun
 * jumlah perubahan seperti halaman sinkron yang sebenarnya. Sebelum penarikan
 * pertama, ketiganya nol, dan angka nol yang dipajang tanpa konteks membuat
 * panitia mengira ada yang gagal.
 */
class PemasanganController extends Controller
{
    public function __construct(private readonly PenarikPeer $penarik) {}

    public function index(): View
    {
        return view('pemasangan.index', [
            'node' => (string) config('sinkron.node'),
            'peran' => (string) config('sinkron.peran'),
            'arena' => (string) config('sinkron.arena'),
            'peer' => collect((array) config('sinkron.peer', []))
                ->map(fn (array $satu) => ['nama' => $satu['nama'], 'url' => $satu['url']])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Satu potongan per panggilan, sama seperti penarikan biasa: perulangannya
     * di browser, supaya satu proses php-cgi tidak ditahan sepanjang
     * penarikan penuh -- dan penarikan pertama adalah yang paling panjang di
     * seluruh umur sebuah node.
     */
    public function tarik(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => ['required', 'string', 'max:64'],
        ]);

        /*
         * Nama peer dicocokkan dengan daftar di `.env` mesin ini sebelum
         * dipakai. PenarikPeer memang menolak nama asing, tapi penolakan itu
         * datang sebagai RuntimeException yang di sini berubah jadi 422 --
         * dan permukaan tanpa login lebih baik memeriksa sendiri apa yang
         * diterimanya daripada mengandalkan lapisan di bawahnya.
         */
        $dikenal = collect((array) config('sinkron.peer', []))
            ->contains(fn (array $satu) => $satu['nama'] === $data['peer']);

        if (! $dikenal) {
            return response()->json(['pesan' => "Peer \"{$data['peer']}\" tidak terdaftar di node ini."], 422);
        }

        try {
            return response()->json($this->penarik->tarikSatuPotongan($data['peer']));
        } catch (Throwable $e) {
            return response()->json(['pesan' => $this->pesanAman($e, $data['peer'])], 422);
        }
    }

    /**
     * Galat penarikan yang aman dibaca orang asing.
     *
     * `/pemasangan` terbuka TANPA LOGIN -- itu memang perlu, karena mesin yang
     * belum punya akun tidak bisa meminta siapa pun masuk. Konsekuensinya apa
     * pun yang tergambar di sini terbaca siapa saja di jaringan gelanggang,
     * termasuk perangkat penonton.
     *
     * Galat basis data membawa nama basis data, nama tabel, nama constraint,
     * dan potongan query beserta nilainya. Uji kotak hitam 11 September 2026
     * memotretnya: satu layar berisi skema separuh aplikasi, di halaman yang
     * tidak menuntut apa pun untuk dibuka.
     *
     * Yang tergambar sekarang kalimat yang menyebut apa yang harus dilakukan;
     * rinciannya masuk log mesin ini, tempat yang memang dibaca saat ada yang
     * salah.
     */
    private function pesanAman(Throwable $e, string $peer): string
    {
        Log::warning('Penarikan pemasangan gagal.', [
            'peer' => $peer,
            'galat' => $e->getMessage(),
        ]);

        if ($e instanceof QueryException) {
            return 'Data dari peer tidak bisa diterapkan di mesin ini. '
                .'Rinciannya tercatat di log node ini (storage/logs). '
                .'Biasanya ini berarti node peer belum menyemai catatan sinkronnya — '
                .'jalankan `php artisan silat:sinkron-semai` di node global, lalu ulangi.';
        }

        return $e->getMessage();
    }
}

