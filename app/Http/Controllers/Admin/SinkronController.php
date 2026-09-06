<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Pemantauan\KesehatanGelanggang;
use App\Support\Sinkron\CatatanKeluar;
use App\Support\Sinkron\Kepemilikan;
use App\Support\Sinkron\PembungkusPaket;
use App\Support\Sinkron\PenarikPeer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pertukaran data antar laptop gelanggang.
 *
 * Dua sisi yang berbeda betul sifatnya, dan sengaja dipisah:
 *
 *   identitas & paket   dipanggil PEER, dijaga token bersama. Tidak butuh
 *                       sesi login: yang mengetuk bukan orang, melainkan
 *                       laptop gelanggang lain.
 *
 *   tarik               dipanggil OPERATOR lewat tombol di halaman sinkron,
 *                       dijaga izin seperti halaman admin lain. Ia yang
 *                       memanggil peer, bukan sebaliknya.
 */
class SinkronController extends Controller
{
    public function __construct(
        private readonly Kepemilikan $kepemilikan,
        private readonly PembungkusPaket $pembungkus,
        private readonly PenarikPeer $penarik,
        private readonly CatatanKeluar $catatan,
    ) {}

    /**
     * Halaman sinkron: siapa kita, siapa tetangga, dan sudah sampai mana.
     *
     * Angka "tertunda" dihitung sebagai selisih kursor, bukan dengan
     * menghitung baris satu per satu. Ia cuma perlu memberi tahu operator
     * apakah ada yang menunggu dan kira-kira seberapa banyak; menghitungnya
     * persis berarti satu kueri agregasi ke tabel yang paling sering ditulis,
     * demi angka yang toh sudah berubah sebelum halamannya selesai dimuat.
     */
    public function index(): View
    {
        $milikSendiri = $this->catatan->kursorTerakhir();

        $kursor = DB::table('sinkron_kursor')->get()->keyBy('peer');

        $peer = collect((array) config('sinkron.peer', []))
            ->map(function (array $satu) use ($kursor) {
                $catatan = $kursor->get($satu['nama']);

                return [
                    'nama' => $satu['nama'],
                    'url' => $satu['url'],
                    'kursor' => (int) ($catatan->kursor_terakhir ?? 0),
                    'ditarik_pada' => $catatan->ditarik_pada ?? null,
                    'baris_diterapkan' => (int) ($catatan->baris_diterapkan ?? 0),
                    'galat' => $catatan->galat_terakhir ?? null,
                ];
            })
            ->values()
            ->all();

        return view('admin.sinkron.index', [
            'kesehatan' => app(KesehatanGelanggang::class)->periksa(),
            'node' => $this->kepemilikan->namaNode(),
            'peran' => (string) config('sinkron.peran'),
            'arena' => (string) config('sinkron.arena'),
            'tokenTerpasang' => (string) config('sinkron.token') !== '',
            'perubahanSendiri' => $milikSendiri,
            'peer' => $peer,
        ]);
    }

    /**
     * Kartu nama node ini.
     *
     * Dipakai peer untuk memastikan ia bicara dengan mesin yang benar sebelum
     * menarik apa pun. Panitia yang salah menyalin alamat IP akan tahu dari
     * sini, bukan dari data gelanggang lain yang diam-diam masuk ke tempat
     * yang salah.
     */
    public function identitas(): JsonResponse
    {
        return response()->json([
            'node' => $this->kepemilikan->namaNode(),
            'peran' => (string) config('sinkron.peran'),
            'arena' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) config('sinkron.arena')),
            ))),
            'kursor' => $this->catatan->kursorTerakhir(),
        ]);
    }

    /**
     * Satu potongan perubahan sejak kursor yang disebut peer.
     *
     * Batasnya diambil dari config, bukan dari permintaan: peer tidak boleh
     * menentukan seberapa besar pekerjaan yang harus dikerjakan mesin ini.
     * Satu permintaan yang menahan proses php-cgi terlalu lama adalah satu
     * proses yang tidak melayani tekanan tombol juri selama itu.
     */
    public function paket(Request $request): JsonResponse
    {
        $sejak = max(0, (int) $request->query('sejak', 0));

        return response()->json($this->pembungkus->bangun($sejak));
    }

    /**
     * Menarik satu potongan dari satu peer.
     *
     * Satu potongan per panggilan, dan browser yang memutar sampai bendera
     * `selesai` naik. Perulangan di sisi server akan menahan proses php-cgi
     * selama seluruh penarikan berlangsung; perulangan di sisi browser
     * melepaskannya di antara potongan, dan memberi operator bilah kemajuan
     * yang benar-benar bergerak.
     */
    public function tarik(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => ['required', 'string', 'max:64'],
        ]);

        try {
            return response()->json($this->penarik->tarikSatuPotongan($data['peer']));
        } catch (RuntimeException $e) {
            return response()->json([
                'pesan' => $e->getMessage(),
            ], 422);
        }
    }
}
