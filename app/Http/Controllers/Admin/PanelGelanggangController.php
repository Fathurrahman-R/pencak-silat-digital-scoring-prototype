<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResourceAction;
use App\Events\Scoring\BabakSusulanBerubah;
use App\Events\Scoring\MatchStateChanged;
use App\Http\Controllers\Concerns\MenjagaAparatGelanggang;
use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Support\Gelanggang\PointerPartaiAktif;
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

    public function __construct(
        private readonly PointerPartaiAktif $pointer,
        private readonly KonfigPanel $konfig,
        private readonly StatePartaiPanel $state,
        private readonly BabakSusulan $susulan,
    ) {}

    public function state(Request $request, Tournament $tournament, Arena $arena): JsonResponse
    {
        $this->pastikanMilik($tournament, $arena);

        return response()->json($this->muatan($tournament, $arena, $request->user()));
    }

    public function kendali(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel(self::PANEL_KENDALI, $request, $tournament, $arena);
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
        return $this->panel('silat.operator', $request, $tournament, $arena);
    }

    public function wasit(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('silat.wasit', $request, $tournament, $arena);
    }

    public function juri(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('silat.juri', $request, $tournament, $arena);
    }

    public function dewanJuri(Request $request, Tournament $tournament, Arena $arena): View
    {
        return $this->panel('silat.dewan-juri', $request, $tournament, $arena);
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
        return $this->panel('silat.keberatan', $request, $tournament, $arena);
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
        return $this->panel('silat.panel-ketua', $request, $tournament, $arena);
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
     * Satu-satunya jalan masuk ke PointerPartaiAktif dari HTTP.
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
            $this->jalankan(fn () => $this->pointer->kosongkan($arena, $request->user()));

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
        }

        return $blok;
    }

    private function panel(string $view, Request $request, Tournament $tournament, Arena $arena): View
    {
        $this->pastikanMilik($tournament, $arena);

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
                'manifestUrl' => route('admin.turnamen.gelanggang.panel.manifest', [$tournament, $arena, $this->peranDariView($view)]),
                'config' => $this->blokPanel($tournament, $arena, null, $request->user()),
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
            'manifestUrl' => route('admin.turnamen.gelanggang.panel.manifest', [$tournament, $arena, $this->peranDariView($view)]),
            /*
             * Aksi per-partai hanya ada kalau partainya ada. Panel kendali
             * boleh dirender pada gelanggang kosong, dan di keadaan itu satu-
             * satunya aksi yang berarti -- memilih partai -- sudah dibawa
             * blokPanel(). Alamat timer dan nilai untuk partai yang belum
             * dipilih bukan cuma tidak berguna, ia tidak bisa dibentuk.
             */
            'config' => $this->blokPanel($tournament, $arena, $match, $request->user())
                + ($match !== null ? $this->konfig->aksi($tournament, $match) : []),
        ]);
    }

    /** Nama peran untuk manifest, diturunkan dari view yang sedang dirender. */
    private function peranDariView(string $view): string
    {
        return match ($view) {
            'silat.wasit' => 'wasit',
            'silat.dewan-juri' => 'dewan-juri',
            'silat.kendali' => 'kendali',
            'silat.keberatan' => 'komisi-protes',
            'silat.panel-ketua' => 'ketua',
            default => 'juri',
        };
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
            throw ValidationException::withMessages(['aksi' => $e->getMessage()]);
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

        if (! $user->hasRole('pengendali-gelanggang')) {
            return;
        }

        abort_unless(
            $arena->pengendali()->whereKey($user->id)->exists(),
            403,
            'Anda bukan pengendali gelanggang ini.',
        );
    }
}
