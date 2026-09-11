<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResourceAction;
use App\Events\Scoring\BabakSusulanBerubah;
use App\Events\Scoring\MatchStateChanged;
use App\Http\Controllers\Concerns\MenjagaAparatGelanggang;
use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\JurusPerformance;
use App\Models\SerahJadwal;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\PenolakanDapatDipaksa;
use App\Support\Gelanggang\PointerTayang;
use App\Support\Gelanggang\SerahTerimaJadwal;
use App\Support\Jurus\PerbandinganBattle;
use App\Support\Jurus\StatePenampilan;
use App\Support\Panel\KonfigPanel;
use App\Support\Panel\StatePartaiPanel;
use App\Support\Scoring\BabakSusulan;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Panel yang mengikuti gelanggang, bukan satu partai.
 *
 * # Kenapa alamatnya berpindah dari partai ke gelanggang
 *
 * Panel juri, wasit, dan dewan wasit juri semuanya terikat URL per-partai.
 * Tiap kali jadwal berganti, setiap petugas harus kembali ke dashboard dan
 * menekan kartu partai berikutnya -- di HP masing-masing, di tengah gelanggang
 * yang sedang berjalan. Juri paling dirugikan: mereka hanya ingin menekan
 * nilai.
 *
 * Sekarang satu perangkat (Pengendali Gelanggang) memindahkan jadwal, dan
 * semua panel gelanggang mengikutinya sendiri.
 *
 * # Kenapa aksinya TIDAK diduplikasi di sini
 *
 * Timer, nilai, hukuman, hitungan, verifikasi, dan VAR tetap memakai rute
 * per-partai yang sudah ada. Klien memperoleh alamatnya dari payload state,
 * dan menghitungnya ulang tiap kali partai aktif berganti.
 *
 * Dua salinan otorisasi untuk aksi yang sama adalah cara paling murah membuat
 * lubang izin: yang satu diperbaiki, yang satu lagi tertinggal.
 */
class PanelGelanggangController extends Controller
{
    use MenjagaAparatGelanggang;

    /**
     * Panel pengendali gelanggang.
     *
     * Berdiri sebagai konstanta karena namanya dipakai dua kali dengan arti
     * berbeda -- sekali untuk dirender, sekali untuk dikecualikan dari layar
     * tunggu -- dan dua string lepas yang harus tetap sama adalah dua string
     * yang suatu saat berbeda.
     */
    private const PANEL_KENDALI = 'silat.kendali';

    /**
     * View tiap peran, per jenis tayangan gelanggang.
     *
     * Alamat panel menyebut PERAN, bukan kategori: satu gelanggang menjalankan
     * Tanding pagi hari dan Jurus siang hari, dan juri yang sama memegang
     * keduanya. Menyuruhnya mengganti alamat di HP tiap kategori berganti
     * adalah persis kerugian yang membuat panel pindah ke alamat gelanggang
     * sejak awal.
     *
     * `null` pada kolom jurus BUKAN 404: peran itu memang tidak bertugas di
     * kategori Jurus. Wasit yang membuka panelnya di gelanggang yang sedang
     * menayangkan Jurus tidak sedang salah alamat -- ia sedang menunggu
     * nomornya sendiri, dan yang pantas ia lihat adalah layar tunggu yang
     * menyebutkan sebabnya.
     *
     * Kendali memetakan ke view yang sama di kedua mode, dan itu bukan
     * kemalasan: `silat.kendali` memang sudah dua-mode (kepala tayangan Jurus
     * dan Antrean Jurus sudah ada di dalamnya), dan tugasnya justru MEMILIH
     * apa yang tayang -- panel yang berganti bentuk mengikuti pilihannya
     * sendiri akan menghilangkan tombol yang baru saja ditekan.
     */
    private const PANEL = [
        'kendali' => ['tanding' => self::PANEL_KENDALI, 'jurus' => self::PANEL_KENDALI],

        /*
         * Papan mode Jurus merender view OPERATOR yang sama, persis seperti
         * papan mode Tanding -- lihat rasional di papan() di bawah.
         *
         * Sempat ada `jurus.papan` tersendiri, hanya-tampil, dan itu keliru
         * dua kali. Kendali timer Jurus dipegang `penampilan-jurus.update`,
         * yang dimiliki Operator IT -- dan alamat yang dibuka Operator IT
         * sepanjang hari adalah panel/papan. Dengan view hanya-tampil, ia
         * membuka alamatnya sendiri dan tidak menemukan tombol Mulai di sana;
         * kendalinya ada di alamat lain, `panel/jurus-operator`. Itu persis
         * kerugian "petugas harus mengetik alamat baru" yang membuat panel
         * pindah ke alamat gelanggang sejak awal.
         *
         * Sekarang satu view, dan izinnya yang menyembunyikan tombol: yang
         * tidak memegang `penampilan-jurus.update` melihat papan tanpa
         * kendali, tanpa satu baris view tambahan.
         */
        'papan' => ['tanding' => 'silat.papan', 'jurus' => 'jurus.operator'],
        'juri' => ['tanding' => 'silat.juri', 'jurus' => 'jurus.juri'],
        'ketua' => ['tanding' => 'silat.panel-ketua', 'jurus' => 'jurus.panel-ketua'],
        'wasit' => ['tanding' => 'silat.wasit', 'jurus' => null],
        'dewan-juri' => ['tanding' => 'silat.dewan-juri', 'jurus' => null],
        'komisi-protes' => ['tanding' => 'silat.keberatan', 'jurus' => null],
    ];

    public function __construct(
        private readonly PointerTayang $pointer,
        private readonly KonfigPanel $konfig,
        private readonly StatePartaiPanel $state,
        private readonly BabakSusulan $susulan,
        private readonly SerahTerimaJadwal $serahTerima,
    ) {}

    public function state(Request $request, Tournament $tournament, Arena $arena): JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);

        return response()->json($this->muatan($tournament, $arena, $request->user()));
    }

    public function kendali(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('kendali', $request, $tournament, $arena);
    }

    /**
     * Papan tampilan gelanggang.
     *
     * Merender view panel operator yang SAMA, bukan salinannya. Seluruh
     * kendalinya sudah dibungkus `@resource(rk('partai', Update|Manage))`, dan
     * `operator-it` baru saja kehilangan kedua izin itu -- jadi tombolnya
     * hilang sendiri tanpa satu baris pun view baru. Salinan view berarti dua
     * berkas yang harus diubah tiap kali tata letak gelanggang bergeser, dan
     * yang kedua akan tertinggal.
     */
    public function papan(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('papan', $request, $tournament, $arena);
    }

    public function wasit(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('wasit', $request, $tournament, $arena);
    }

    public function juri(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('juri', $request, $tournament, $arena);
    }

    public function dewanJuri(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('dewan-juri', $request, $tournament, $arena);
    }

    /**
     * Panel Wasit Komisi Protes.
     *
     * Perannya tidak punya panel lain, jadi panel keberatan ITULAH panelnya --
     * bukan halaman tambahan yang harus dicari. Alamatnya per gelanggang,
     * sehingga ia ikut berpindah partai tanpa disentuh.
     */
    public function komisiProtes(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('komisi-protes', $request, $tournament, $arena);
    }

    /**
     * Panel Ketua Pertandingan untuk satu gelanggang.
     *
     * Ringkasan lintas gelanggang (KetuaPertandinganController) TIDAK diganti
     * olehnya: tugas kejuaraannya memang selebar itu. Yang ditambahkan di sini
     * adalah layar untuk tugas gelanggangnya, yang mengikuti partai aktif
     * seperti panel petugas lain.
     */
    public function ketua(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('ketua', $request, $tournament, $arena);
    }

    /**
     * Manifest PWA per GELANGGANG, bukan per partai.
     *
     * Yang lama menunjuk `start_url` ke satu partai. Ikon yang dipasang di
     * layar utama pagi hari mengantar petugas ke partai pertama sepanjang sisa
     * hari itu -- pasti keliru sejak partai kedua. Alamat gelanggang tidak
     * pernah basi.
     *
     * Nama dan ikonnya menyebut peran DAN gelanggangnya: petugas yang memegang
     * dua gelanggang punya dua ikon di layar utamanya, dan keduanya harus bisa
     * dibedakan tanpa dibuka.
     */
    public function manifest(Request $request, Tournament $tournament, Arena $arena, string $peran): JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $dikenal = [
            'juri' => ['Panel Juri', 'Juri', 'Papan tombol juri untuk penilaian pertandingan Tanding.'],
            'wasit' => ['Panel Wasit', 'Wasit', 'Papan hukuman dan hitungan teknik untuk wasit gelanggang.'],
            'dewan-juri' => ['Panel Dewan Wasit Juri', 'Dewan', 'Peninjauan nilai, pembatalan, dan pengesahan hasil partai.'],
            'kendali' => ['Kendali Gelanggang', 'Kendali', 'Timer, perpindahan babak, dan pergantian jadwal gelanggang.'],
            'papan' => ['Papan Gelanggang', 'Papan', 'Skor, timer, dan nama pesilat untuk layar gelanggang.'],
            'komisi-protes' => ['Panel Komisi Protes', 'Protes', 'Protes VAR dan protes manajer untuk satu gelanggang.'],
            'ketua' => ['Panel Ketua Pertandingan', 'Ketua', 'Skor berjalan, protes, dan hasil partai satu gelanggang.'],
        ];

        abort_unless(isset($dikenal[$peran]), 404);

        [$nama, $pendek, $keterangan] = $dikenal[$peran];
        $alamat = route("admin.turnamen.gelanggang.panel.{$peran}", [$tournament, $arena]);

        return response()->json([
            'name' => "{$nama} — {$arena->name}",
            'short_name' => "{$pendek} {$arena->code}",
            'description' => $keterangan,
            'start_url' => $alamat,
            'scope' => $alamat,
            'display' => 'fullscreen',
            'orientation' => 'portrait',
            'background_color' => '#0b0b0c',
            'theme_color' => '#0b0b0c',
            /*
             * PNG lebih dulu, vektor menyusul -- sebagian peluncur (iOS Safari
             * yang paling menonjol) menolak ikon vektor dan akan melewati
             * manifest ini seluruhnya kalau tidak ada raster yang bisa dipakai.
             */
            'icons' => [
                ['src' => '/icons/juri-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/icons/juri-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/icons/juri.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Menetapkan partai yang ditayangkan gelanggang.
     *
     * Satu-satunya jalan masuk ke PointerTayang dari HTTP.
     */
    public function pilihPartai(Request $request, Tournament $tournament, Arena $arena): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        $data = $request->validate([
            'match_id' => ['nullable', 'integer'],
            /*
             * Pindah paksa meninggalkan partai yang masih berjalan. Dipisah
             * dari tombol biasa dan tidak pernah jadi bawaan: pengendali harus
             * menyatakannya, bukan menemukannya sebagai efek samping.
             */
            'paksa' => ['sometimes', 'boolean'],
        ]);

        if (($data['match_id'] ?? null) === null) {
            $this->jalankan(fn () => $this->pointer->kosongkan(
                $arena,
                $request->user(),
                paksa: (bool) ($data['paksa'] ?? false),
            ));

            return $this->balas($request, $tournament, $arena, 'Gelanggang dikosongkan.');
        }

        $match = SilatMatch::whereKey($data['match_id'])->firstOrFail();
        $this->pastikanPartaiMilik($tournament, $match);

        $this->jalankan(fn () => $this->pointer->tunjuk(
            $arena,
            $match,
            $request->user(),
            paksa: (bool) ($data['paksa'] ?? false),
        ));

        return $this->balas($request, $tournament, $arena, "Gelanggang berpindah ke partai {$match->id}.");
    }

    /**
     * Pengendali memilih penampilan Jurus yang ditayangkan gelanggang.
     *
     * Kembaran pilihPartai() untuk kategori Jurus. `performance_id` kosong
     * berarti mengosongkan gelanggang -- bentuk yang sama, supaya panel
     * kendali tidak perlu dua alur berbeda untuk satu tombol.
     */
    public function pilihPenampilan(Request $request, Tournament $tournament, Arena $arena): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        $data = $request->validate([
            'performance_id' => ['nullable', 'integer'],
            'paksa' => ['sometimes', 'boolean'],
        ]);

        if (($data['performance_id'] ?? null) === null) {
            $this->jalankan(fn () => $this->pointer->kosongkan(
                $arena,
                $request->user(),
                paksa: (bool) ($data['paksa'] ?? false),
            ));

            return $this->balas($request, $tournament, $arena, 'Gelanggang dikosongkan.');
        }

        $performance = JurusPerformance::whereKey($data['performance_id'])->firstOrFail();

        abort_unless($performance->jurusEvent->tournament_id === $tournament->id, 404);

        $this->jalankan(fn () => $this->pointer->tunjukPenampilan(
            $arena,
            $performance,
            $request->user(),
            paksa: (bool) ($data['paksa'] ?? false),
        ));

        return $this->balas($request, $tournament, $arena, "Gelanggang berpindah ke penampilan {$performance->id}.");
    }

    /**
     * Melepas satu partai atau penampilan ke gelanggang lain.
     *
     * Pelepas berhenti menayangkannya seketika, tapi baris itu belum berpindah
     * pemilik: ia menggantung sebagai penawaran sampai pengendali tujuan
     * mengambilnya. Jendela itu SENGAJA terlihat di kedua layar -- jendela
     * yang disembunyikan adalah jendela yang baru ketahuan saat pesilat sudah
     * berdiri di matras yang salah.
     */
    public function lepasKeGelanggang(Request $request, Tournament $tournament, Arena $arena): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        $data = $request->validate([
            'ke_arena_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:tanding,jurus'],
            'baris_id' => ['required', 'integer'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        $tujuan = Arena::whereKey($data['ke_arena_id'])->firstOrFail();
        $this->pastikanMilik($tournament, $tujuan);

        $baris = $data['jenis'] === SerahJadwal::TANDING
            ? SilatMatch::whereKey($data['baris_id'])->firstOrFail()
            : JurusPerformance::whereKey($data['baris_id'])->firstOrFail();

        if ($baris instanceof SilatMatch) {
            $this->pastikanPartaiMilik($tournament, $baris);
        } else {
            abort_unless($baris->jurusEvent->tournament_id === $tournament->id, 404);
        }

        $this->jalankan(fn () => $this->serahTerima->lepas(
            $arena,
            $tujuan,
            $baris,
            $request->user(),
            $data['alasan'] ?? null,
        ));

        return $this->balas($request, $tournament, $arena, "Dilepas ke {$tujuan->name}, menunggu diambil.");
    }

    /** Pelepas menarik kembali penawarannya, selama belum diambil. */
    public function batalkanLepas(Request $request, Tournament $tournament, Arena $arena, SerahJadwal $serah): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        abort_unless($serah->arena_id === $arena->id, 403, 'Penawaran ini bukan milik gelanggang ini.');

        $this->jalankan(fn () => $this->serahTerima->batalkan($serah, $request->user()));

        return $this->balas($request, $tournament, $arena, 'Penawaran dibatalkan.');
    }

    /**
     * Gelanggang tujuan mengambil baris yang ditawarkan kepadanya.
     *
     * Di sinilah `arena_id` berpindah, dan hanya di sini -- sesudah adopsinya
     * tercatat sebagai baris milik node ini.
     */
    public function ambilLepasan(Request $request, Tournament $tournament, Arena $arena, SerahJadwal $serah): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        abort_unless($serah->ke_arena_id === $arena->id, 403, 'Penawaran ini tidak ditujukan ke gelanggang ini.');

        $this->jalankan(fn () => $this->serahTerima->ambil($serah, $request->user()));

        return $this->balas($request, $tournament, $arena, 'Masuk ke jadwal gelanggang ini.');
    }

    /** Panel juri Jurus, mengikuti penampilan yang ditunjuk gelanggang. */
    public function jurusJuri(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panelJurus('jurus.juri', 'juri', $request, $tournament, $arena);
    }

    /** Panel operator Jurus, mengikuti penampilan yang ditunjuk gelanggang. */
    public function jurusOperator(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panelJurus('jurus.operator', 'papan', $request, $tournament, $arena);
    }

    /**
     * State penampilan yang sedang ditayangkan gelanggang.
     *
     * Beralamat gelanggang, bukan penampilan: itulah yang membuat panel juri
     * ikut berpindah sendiri saat pengendali mengganti nomor. Alamat per
     * penampilan basi tepat pada saat pergantian, dan yang menanggungnya juri
     * yang harus mengetik ulang alamat di HP-nya.
     *
     * Muatannya sama persis dengan endpoint per penampilan -- keduanya lewat
     * App\Support\Jurus\StatePenampilan.
     */
    public function jurusState(Tournament $tournament, Arena $arena): JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);

        $performance = $this->pointer->penampilanAktif($arena);

        if ($performance === null) {
            return response()->json([
                'penampilan_aktif' => false,
                'pesan' => 'Gelanggang ini belum menayangkan penampilan.',
            ]);
        }

        return response()->json([
            'penampilan_aktif' => true,
            'aksi' => $this->aksiJurus($tournament, $performance),

            /*
             * Perbandingan kedua sudut, kalau penampilan ini berdiri di dalam
             * battle. Dihitung SATU kali di sini dan dipakai keempat panel yang
             * mengikuti gelanggang -- papan, juri, ketua, operator. Empat
             * salinan perhitungan berarti empat angka yang suatu saat berbeda,
             * dan pelatih yang membandingkan dua layar lalu menemukan dua angka
             * punya alasan sah untuk tidak percaya pada keduanya.
             *
             * Ongkosnya dibayar hanya oleh nomor berformat battle, dan hanya di
             * endpoint Jurus -- bukan di `state` Tanding yang ditarik tiap
             * tekanan tombol juri.
             */
            'komparasi' => $performance->battle !== null
                ? app(PerbandinganBattle::class)($performance->battle)
                : null,
        ] + app(StatePenampilan::class)($performance));
    }

    /**
     * Merender panel Jurus untuk penampilan yang sedang ditunjuk.
     *
     * Gelanggang kosong mendapat layar tunggu yang sama dengan panel Tanding,
     * bukan 404: pagi sebelum nomor pertama dan jeda antar nomor adalah
     * keadaan normal, dan yang membukanya tidak sedang salah alamat.
     */
    private function panelJurus(?string $view, string $peran, Request $request, Tournament $tournament, Arena $arena): View
    {
        $this->pastikanMilik($tournament, $arena);

        $performance = $this->pointer->penampilanAktif($arena);

        /*
         * Peran yang tidak bertugas di Jurus mendapat layar tunggu, bukan 404.
         *
         * Wasit, Dewan Wasit Juri, dan Komisi Protes tidak punya tugas di
         * nomor Jurus. Yang membuka panelnya saat gelanggang sedang menayangkan
         * Jurus tidak sedang salah alamat -- ia sedang menunggu nomor Tanding
         * berikutnya, dan alamat yang dipegangnya memang alamat yang benar.
         * 404 di situ membuatnya mengira panelnya rusak.
         */
        if ($view === null) {
            return view('silat.menunggu-partai', [
                'tournament' => $tournament,
                'arena' => $arena,
                'manifestUrl' => route('admin.turnamen.gelanggang.panel.manifest', [$tournament, $arena, $peran]),
                'config' => $this->blokPanel($tournament, $arena, null, $request->user())
                    + ['menunggu' => true, 'sebabMenunggu' => 'jurus'],
            ]);
        }

        if ($performance === null) {
            /*
             * Layar tunggu yang SAMA dengan panel Tanding, bukan salinannya.
             * Kalimatnya menyebut "partai" dan itu memang tepat: yang ditunggu
             * sama-sama keputusan pengendali, dan petugas Jurus membaca layar
             * yang bentuknya sudah dikenalnya dari matras sebelah.
             */
            return view('silat.menunggu-partai', [
                'tournament' => $tournament,
                'arena' => $arena,
                'manifestUrl' => null,
                'config' => $this->blokPanel($tournament, $arena, null, $request->user()) + ['menunggu' => true],
            ]);
        }

        $performance->load('jurusEvent', 'registration.athletes', 'registration.contingent');

        $config = $this->aksiJurus($tournament, $performance) + [
            'state' => route('admin.turnamen.gelanggang.panel.jurus-state', [$tournament, $arena]),
            /*
             * Panel ini mengikuti GELANGGANG, jadi ia harus mendengar channel
             * gelanggang -- bukan cuma channel penampilan yang sedang tayang.
             *
             * Tanpa itu, pengendali yang berpindah ke partai Tanding atau ke
             * penampilan lain meninggalkan panel juri Jurus memajang
             * penampilan yang sudah tidak ada di matras, dengan tombol nilai
             * yang masih hidup. Ditemukan begitu di peramban: dua setengah
             * detik sesudah gelanggang beralih, panelnya belum bergerak sama
             * sekali, dan baru benar setelah dimuat ulang dengan tangan.
             */
            'arenaId' => $arena->id,
        ];

        if ($view === 'jurus.juri') {
            $config['judgeUserId'] = $request->user()->id;
        }

        return view($view, [
            'tournament' => $tournament,
            'arena' => $arena,
            'performance' => $performance,
            'config' => $config,
        ]);
    }

    /**
     * Alamat aksi penampilan.
     *
     * Tetap per PENAMPILAN, tidak ikut pindah ke alamat gelanggang: aksi
     * menyebut sasaran yang pasti, dan nilai yang dikirim ke "apa pun yang
     * sedang tayang" akan mendarat di penampilan yang keliru begitu pengendali
     * berpindah di antara juri menekan dan permintaannya sampai.
     *
     * Alamat-alamat ini ikut terkirim ulang tiap kali state ditarik, jadi
     * panel selalu memegang sasaran yang mutakhir.
     *
     * @return array<string, mixed>
     */
    private function aksiJurus(Tournament $tournament, JurusPerformance $performance): array
    {
        return [
            'performanceId' => $performance->id,
            'battleId' => $performance->jurus_battle_id,
            'mulai' => route('admin.turnamen.jurus.penampilan.timer.mulai', [$tournament, $performance]),
            'berhenti' => route('admin.turnamen.jurus.penampilan.timer.berhenti', [$tournament, $performance]),
            'nilai' => route('admin.turnamen.jurus.penampilan.nilai', [$tournament, $performance]),
            'penguranganJuri' => route('admin.turnamen.jurus.penampilan.pengurangan-juri', [$tournament, $performance]),
            'penguranganPengawas' => route('admin.turnamen.jurus.penampilan.pengurangan-pengawas', [$tournament, $performance]),
            'penguranganBatal' => route('admin.turnamen.jurus.penampilan.pengurangan.batal', [$tournament, $performance, '__ID__']),
            'diskualifikasi' => route('admin.turnamen.jurus.penampilan.diskualifikasi', [$tournament, $performance]),
            'sahkan' => route('admin.turnamen.jurus.penampilan.sahkan', [$tournament, $performance]),

            /*
             * Penetapan pemenang battle, dari panel yang menampilkan dasar
             * keputusannya. Null untuk nomor berformat peringkat -- panel
             * memakai ketiadaannya untuk tidak menggambar tombolnya sama
             * sekali, bukan menggambar tombol yang pasti ditolak server.
             */
            'putuskanBattle' => $performance->jurus_battle_id !== null
                ? route('admin.turnamen.jurus.battle.putuskan', [$tournament, $performance->jurus_battle_id])
                : null,
        ];
    }

    /**
     * Membuka babak lama untuk pencatatan susulan.
     *
     * Wewenang terberat di gelanggang: ia melonggarkan penjagaan babak yang
     * sudah ditutup. Karena itu dijaga `kendali-gelanggang.manage`, bukan
     * `partai.update` yang dipegang siapa pun yang boleh menekan timer.
     */
    public function bukaBabak(Request $request, Tournament $tournament, Arena $arena): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        $match = $this->pointer->partaiAktif($arena)
            ?? abort(422, 'Gelanggang ini belum menayangkan partai mana pun.');

        $data = $request->validate(['babak' => ['required', 'integer', 'min:1']]);

        $this->jalankan(fn () => $this->susulan->buka($match, (int) $data['babak'], $request->user()));

        $this->siarkanSusulan($match->refresh());

        return $this->balas($request, $tournament, $arena, "Babak {$data['babak']} dibuka untuk input susulan.");
    }

    public function tutupBabak(Request $request, Tournament $tournament, Arena $arena): RedirectResponse|JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);
        $this->pastikanPengendali($arena, $request->user());

        $match = $this->pointer->partaiAktif($arena)
            ?? abort(422, 'Gelanggang ini belum menayangkan partai mana pun.');

        $babak = $match->susulan_round;
        $jedaOtomatis = $match->susulan_jeda_otomatis;

        $this->jalankan(fn () => $this->susulan->tutup($match, $request->user()));

        $this->siarkanSusulan($match->refresh());

        $lanjut = $jedaOtomatis
            ? "Babak {$match->current_round} dilanjutkan."
            : "Babak {$match->current_round} tetap jeda.";

        return $this->balas($request, $tournament, $arena, "Susulan babak {$babak} ditutup. {$lanjut}");
    }

    /**
     * Dua siaran sekaligus, dan itu disengaja.
     *
     * `babak-susulan.berubah` dibaca panel yang sudah memasang pendengar
     * barunya; `partai.berubah` menjangkau panel yang belum -- versi lama yang
     * masih terbuka di HP juri sejak sebelum pembaruan dipasang. Yang kedua
     * memaksanya menarik ulang state, dan di situ ia menemukan spanduk
     * susulannya.
     */
    private function siarkanSusulan(SilatMatch $match): void
    {
        try {
            BabakSusulanBerubah::dispatch($match);
            MatchStateChanged::dispatch($match);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Muatan state gelanggang: state partai yang sudah ada, plus blok `panel`.
     *
     * Gelanggang tanpa partai aktif membalas `match: null` dan panel merender
     * layar tunggu -- bukan 404. Pengendali yang belum memilih partai bukan
     * kekeliruan alamat.
     *
     * @return array<string, mixed>
     */
    private function muatan(Tournament $tournament, Arena $arena, ?User $untuk): array
    {
        $match = $this->pointer->partaiAktif($arena);

        $dasar = $match !== null
            ? ($this->state)($match, $untuk)
            : ['match' => null];

        return $dasar + ['panel' => $this->blokPanel($tournament, $arena, $match, $untuk)];
    }

    /** @return array<string, mixed> */
    private function blokPanel(Tournament $tournament, Arena $arena, ?SilatMatch $match, ?User $untuk): array
    {
        $blok = $this->konfig->tetap($tournament, $arena, $untuk, 'gelanggang', $match)
            + ['aksi' => $match !== null ? $this->konfig->aksi($tournament, $match) : []]
            + [
                'bukaSusulan' => route('admin.turnamen.gelanggang.panel.babak-susulan.buka', [$tournament, $arena]),
                'tutupSusulan' => route('admin.turnamen.gelanggang.panel.babak-susulan.tutup', [$tournament, $arena]),
                'bolehKendali' => $this->bolehMengendalikan($arena, $untuk),

                /*
                 * Jenis tayangan gelanggang: 'tanding', 'jurus', atau null.
                 *
                 * Satu field, NOL query tambahan -- relasi `tayang` sudah
                 * dimuat untuk pointer di baris-baris sebelumnya. Yang
                 * membacanya panel Tanding: begitu gelanggang beralih ke
                 * Jurus, bentuk halaman yang pantas dirender berubah, dan
                 * panel yang cuma menyerap state baru akan memajang partai
                 * kosong dengan tombol yang masih hidup. Servernya yang tahu
                 * halaman mana yang pantas; panel cukup bertanya lagi.
                 */
                'tayang' => $arena->tayang?->tayang_type,
            ];

        /*
         * Antrean hanya untuk yang boleh mengendalikan gelanggang.
         *
         * Panel juri menarik endpoint yang sama. Mengirim jadwal seluruh
         * gelanggang lewat endpoint penilaian berarti izin `penilaian.create`
         * diam-diam ikut memberi akses baca jadwal -- kebocoran yang tidak
         * pernah dinyatakan di mana pun.
         */
        if ($untuk !== null && $untuk->can(rk('kendali-gelanggang', ResourceAction::View))) {
            $blok['antrean'] = $this->pointer->antrean($arena)->map(fn (SilatMatch $partai) => [
                'id' => $partai->id,
                'urutan' => $partai->order_in_arena,
                'status' => $partai->status,
                'kelas' => $partai->bracket?->weightClass?->name,
                'merah' => $partai->red?->athletes->pluck('name')->implode(', '),
                'biru' => $partai->blue?->athletes->pluck('name')->implode(', '),
                'aktif' => $partai->id === $arena->active_match_id,
            ])->all();

            /*
             * Kategori Jurus ikut dikirim ke panel kendali.
             *
             * Satu gelanggang menayangkan satu hal -- partai Tanding ATAU
             * penampilan Jurus -- tapi panel kendali sampai sekarang cuma
             * mengenal yang pertama. Akibatnya terukur di peramban: gelanggang
             * yang sedang menayangkan penampilan Jurus membuat panel
             * pengendalinya menulis "Belum ada partai dipilih", seolah matras
             * itu menganggur, sementara juri Jurus di gelanggang yang sama
             * sedang menatap penampilan yang berjalan.
             *
             * Yang dikirim: apa yang sedang tayang (kalau itu Jurus), dan
             * antrean penampilan gelanggang ini supaya pengendali bisa
             * memindahkannya tanpa keluar dari panel.
             */
            $penampilanTayang = $this->pointer->penampilanAktif($arena);

            $blok['jurus'] = [
                'pilih' => route('admin.turnamen.gelanggang.panel.penampilan-aktif', [$tournament, $arena]),
                'panel' => route('admin.turnamen.gelanggang.panel.jurus-operator', [$tournament, $arena]),
                'tayang' => $penampilanTayang === null ? null : [
                    'id' => $penampilanTayang->id,
                    'nomor' => $penampilanTayang->jurusEvent?->nama(),
                    'peserta' => $penampilanTayang->registration?->athletes->pluck('name')->implode(', '),
                    'kontingen' => $penampilanTayang->registration?->contingent?->name,
                    'status' => $penampilanTayang->status,
                ],
                'antrean' => $this->pointer->antreanJurus($arena)->map(fn (JurusPerformance $satu) => [
                    'id' => $satu->id,
                    'urutan' => $satu->order_in_arena,
                    'status' => $satu->status,
                    'nomor' => $satu->jurusEvent?->nama(),
                    'peserta' => $satu->registration?->athletes->pluck('name')->implode(', '),
                    'kontingen' => $satu->registration?->contingent?->name,
                    'aktif' => $penampilanTayang !== null && $satu->id === $penampilanTayang->id,

                    /*
                     * Penanda "sudut dari sebuah battle".
                     *
                     * Serah-terima memindahkan SATU baris, dan satu sudut
                     * battle tidak boleh berpindah sendirian -- kedua sudutnya
                     * dimainkan berurutan di matras yang sama (Pasal 12.1.d.7),
                     * dan yang tertinggal akan berdiri sendirian di antrean
                     * tanpa penjelasan. SerahTerimaJadwal menolaknya, jadi
                     * panel tidak menawarkan tombolnya -- tombol yang pasti
                     * ditolak lebih buruk daripada tombol yang tidak ada.
                     */
                    'battle' => $satu->jurus_battle_id,
                ])->all(),
            ];

            /*
             * Serah-terima antar gelanggang ikut di sini, bukan di endpoint
             * `state` yang ditarik tiap panel: daftarnya berubah beberapa kali
             * sehari, sementara `state` ditarik tiap tekanan tombol juri.
             * Menaruhnya di sana berarti dua query tambahan pada jalur
             * terpanas untuk data yang hampir tidak pernah berubah.
             *
             * Keduanya dikirim bersama supaya pengendali tidak perlu keluar ke
             * layar lain untuk memindahkan jadwal maupun menerimanya.
             */
            $blok['serah'] = [
                'gelanggang' => Arena::where('tournament_id', $arena->tournament_id)
                    ->aktif()
                    ->whereKeyNot($arena->id)
                    ->orderBy('sort_order')
                    ->get(['id', 'name'])
                    ->map(fn (Arena $lain) => ['id' => $lain->id, 'nama' => $lain->name])
                    ->all(),
                'menunggu' => $this->serahTerima->menungguDiambil($arena)
                    ->map(fn ($satu) => [
                        'id' => $satu->id,
                        'jenis' => $satu->baris_type,
                        'baris_id' => $satu->baris_id,
                        'tujuan' => $satu->tujuan?->name,
                        'alasan' => $satu->alasan,
                    ])->all(),
                'ditawarkan' => $this->serahTerima->ditawarkanKe($arena)
                    ->map(fn ($satu) => [
                        'id' => $satu->id,
                        'jenis' => $satu->baris_type,
                        'baris_id' => $satu->baris_id,
                        'asal' => $satu->arena?->name,
                        'alasan' => $satu->alasan,
                    ])->all(),
                /*
                 * Partai yang ada di gelanggang ini tapi belum punya nomor
                 * urut. Hampir selalu hasil serah-terima: adopsi sengaja tidak
                 * membawa nomor urut gelanggang asal, karena menempelkannya
                 * akan menyisipkan partai di tengah antrean orang lain.
                 *
                 * Berdiri sebagai daftar sendiri, bukan mengandalkan antrean:
                 * `antrean()` menaruh yang tanpa nomor di paling belakang lalu
                 * memotong dua puluh, dan gelanggang dengan 281 partai
                 * terjadwal membuat partai yang baru masuk tidak pernah
                 * terlihat sama sekali. Ditemukan begitu lewat blackbox
                 * testing -- penawaran terserap, lalu partainya lenyap.
                 */
                'baruMasuk' => SilatMatch::where('arena_id', $arena->id)
                    ->whereNull('order_in_arena')
                    ->where('status', '!=', SilatMatch::STATUS_SELESAI)
                    ->with(['red.athletes', 'blue.athletes', 'bracket.weightClass'])
                    ->orderBy('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (SilatMatch $partai) => [
                        'id' => $partai->id,
                        'kelas' => $partai->bracket?->weightClass?->name,
                        'merah' => $partai->red?->athletes->pluck('name')->implode(', '),
                        'biru' => $partai->blue?->athletes->pluck('name')->implode(', '),
                    ])->all(),
                'lepas' => route('admin.turnamen.gelanggang.panel.lepas', [$tournament, $arena]),
                'batal' => route('admin.turnamen.gelanggang.panel.lepas.batal', [$tournament, $arena, '__ID__']),
                'ambil' => route('admin.turnamen.gelanggang.panel.lepas.ambil', [$tournament, $arena, '__ID__']),
            ];
        }

        return $blok;
    }

    private function panel(string $peran, Request $request, Tournament $tournament, Arena $arena): View
    {
        $this->pastikanMilik($tournament, $arena);

        $view = self::PANEL[$peran]['tanding'];

        /*
         * Gelanggang yang sedang menayangkan Jurus merender panel Jurus, di
         * alamat yang sama.
         *
         * Kendali dikecualikan: ia yang MEMILIH apa yang tayang, dan panel yang
         * berganti bentuk mengikuti pilihannya sendiri akan menyembunyikan
         * tombol yang baru saja ditekan.
         *
         * Dibaca lewat relasi `tayang` yang sudah dimuat partaiAktif() di bawah
         * -- Eloquent men-cache relasinya, jadi tidak ada query tambahan pada
         * jalur yang dipakai tiap panel dibuka.
         */
        if ($peran !== 'kendali' && $arena->tayang?->menayangkanJurus()) {
            return $this->panelJurus(self::PANEL[$peran]['jurus'], $peran, $request, $tournament, $arena);
        }

        $match = $this->pointer->partaiAktif($arena);

        if ($match !== null) {
            $this->pastikanAparatPartai($match, $request->user());
        }

        /*
         * Gelanggang yang belum dipilihkan partai bukan kekeliruan alamat.
         * Panel-panel di bawah menyusun kepalanya dari data partai, jadi
         * mereka tidak bisa dirender tanpa satu -- yang tampil layar tunggu,
         * bukan 404 maupun halaman setengah jadi.
         *
         * Panel kendali dikecualikan, dan pengecualian itu bukan kerapian:
         * ia satu-satunya panel yang tugasnya MEMILIH partai. Mengalihkannya
         * ke layar tunggu membuat gelanggang yang kosong tidak punya jalan
         * keluar sama sekali -- layar itu menyuruh pengendali memilih partai
         * sambil menyembunyikan satu-satunya tombol untuk memilihnya, dan
         * keadaan kosong itu persis keadaan tiap gelanggang setiap pagi
         * sebelum partai pertama. `silat.kendali` memang sanggup dirender
         * tanpa partai: kepalanya menulis "Belum ada partai dipilih" dan
         * antreannya datang dari daftar gelanggang, bukan dari partai aktif.
         */
        if ($match === null && $view !== self::PANEL_KENDALI) {
            return view('silat.menunggu-partai', [
                'tournament' => $tournament,
                'arena' => $arena,
                'manifestUrl' => route('admin.turnamen.gelanggang.panel.manifest', [$tournament, $arena, $peran]),
                /*
                 * Penanda `menunggu` memberi tahu partaiPanel bahwa halaman
                 * yang memuatnya TIDAK punya markup panel.
                 *
                 * Siaran `gelanggang.partai` sampai ke layar ini dan state
                 * barunya terserap dengan benar, tapi tidak ada satu pun
                 * elemen yang menggambarnya -- tanpa penanda ini, layar tunggu
                 * menjanjikan panel yang terbuka sendiri lalu diam selamanya,
                 * dan juri baru sadar setelah seseorang menyuruhnya memuat
                 * ulang di tengah partai.
                 */
                'config' => $this->blokPanel($tournament, $arena, null, $request->user()) + ['menunggu' => true],
            ]);
        }

        return view($view, [
            'tournament' => $tournament,
            'arena' => $arena,
            'match' => $match,
            /*
             * Manifest PWA masih menunjuk alamat per-partai. Panel juri
             * memakainya apa adanya; alamat per-gelanggang yang tidak pernah
             * basi menyusul bersama pangkasan flow petugas.
             */
            'manifestUrl' => route('admin.turnamen.gelanggang.panel.manifest', [$tournament, $arena, $peran]),
            /*
             * Aksi per-partai hanya ada kalau partainya ada. Panel kendali
             * boleh dirender pada gelanggang kosong, dan di keadaan itu satu-
             * satunya aksi yang berarti -- memilih partai -- sudah dibawa
             * blokPanel(). Alamat timer dan nilai untuk partai yang belum
             * dipilih bukan cuma tidak berguna, ia tidak bisa dibentuk.
             */
            'config' => $this->blokPanel($tournament, $arena, $match, $request->user())
                + ($match !== null ? $this->konfig->aksi($tournament, $match) : [])
                /*
                 * Panel ini harus memuat ulang kalau gelanggang beralih ke
                 * Jurus -- kecuali panel kendali, yang justru yang menekan
                 * peralihannya dan tetap sama bentuknya di kedua mode.
                 */
                + ['ikutiTayang' => $peran !== 'kendali'],
        ]);
    }

    private function balas(Request $request, Tournament $tournament, Arena $arena, string $pesan): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json($this->muatan($tournament, $arena->refresh(), $request->user()));
        }

        return back()->with('success', $pesan);
    }

    /** Menerjemahkan RuntimeException domain jadi error validasi yang dibaca panel. */
    private function jalankan(Closure $aksi): mixed
    {
        try {
            return $aksi();
        } catch (RuntimeException $e) {
            /*
             * Penolakan yang bisa ditembus paksa membawa penanda sendiri.
             *
             * Panel kendali menyalakan tombol "paksa" dari kunci ini, bukan
             * dari potongan kalimat pesannya -- redaksi boleh diperbaiki tanpa
             * diam-diam mematikan satu-satunya jalan keluar pengendali.
             */
            throw ValidationException::withMessages(
                ['aksi' => $e->getMessage()]
                + ($e instanceof PenolakanDapatDipaksa
                    ? ['dapat_dipaksa' => 'Aksi ini masih bisa dijalankan paksa.']
                    : []),
            );
        }
    }

    private function pastikanMilik(Tournament $tournament, Arena $arena): void
    {
        abort_unless($arena->tournament_id === $tournament->id, 404);
    }

    private function pastikanPartaiMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }

    /**
     * Pengendali hanya boleh mengendalikan gelanggangnya sendiri.
     *
     * Ketua Pertandingan dikecualikan: wewenangnya memang lintas gelanggang,
     * dan ia jalan keluar saat perangkat pengendali mati di tengah acara.
     */
    private function pastikanPengendali(Arena $arena, ?User $user): void
    {
        abort_if($user === null, 403);

        abort_unless(
            $this->bolehMengendalikan($arena, $user),
            403,
            'Anda bukan pengendali gelanggang ini.',
        );
    }

    /**
     * Apakah orang ini boleh MENGENDALIKAN gelanggang ini, bukan sekadar
     * melihatnya.
     *
     * Peran `pengendali-gelanggang` dibatasi ke gelanggang yang memang
     * dipegangnya; peran lain yang punya izinnya (panitia, ketua) tidak
     * dibatasi -- merekalah yang menambal saat pengendali berhalangan.
     *
     * Dipakai dua kali dengan arti yang sama: menolak aksi di server, dan
     * memutuskan apakah tombolnya digambar sama sekali. Panel kendali yang
     * memajang tombol yang pasti dijawab 403 lebih berbahaya daripada panel
     * yang tidak memajangnya -- yang menekan "Kosongkan gelanggang" di
     * gelanggang sebelah tidak selalu membaca pesan galatnya.
     */
    private function bolehMengendalikan(Arena $arena, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $user->hasRole('pengendali-gelanggang')) {
            return true;
        }

        return $arena->pengendali()->whereKey($user->id)->exists();
    }
}
