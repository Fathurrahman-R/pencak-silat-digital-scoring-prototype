<?php

namespace App\Http\Controllers;

use App\Enums\ResourceAction;
use App\Models\Arena;
use App\Models\ArenaOfficial;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Beranda\PekerjaanMenunggu;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Scoring\AlasanMenang;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Kartu partai yang ditampilkan sekaligus. Aparat mencari partai
     * berikutnya di sini, bukan membaca seluruh jadwalnya.
     */
    public const MAKS_PENUGASAN = 12;

    public function __construct(
        private readonly NavigationBuilder $navigasi,
        private readonly PekerjaanMenunggu $pekerjaan,
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $turnamen = $this->navigasi->turnamenAktif();

        if ($alihkan = $this->alihkanKePanelGelanggang($turnamen)) {
            return $alihkan;
        }

        $ringkasan = resource_allows(rk('turnamen', ResourceAction::View));
        $penugasan = $this->penugasanSaya($turnamen);

        /*
         * Ringkasan kejuaraan dihitung HANYA kalau memang akan dirender.
         *
         * Ketiganya -- pekerjaan yang menunggu, antrean gelanggang, hasil
         * terakhir -- dipakai di dalam satu blok @if ($tampilkanRingkasan) di
         * view, tapi sebelumnya dihitung untuk semua orang. Juri dan wasit
         * membayar ketiganya pada tiap pembukaan beranda dan tidak pernah
         * melihat satu pun barisnya; diukur di dataset besar, itu bagian
         * terbesar dari 683 ms yang tersisa sesudah kartu partai dipangkas.
         *
         * Merekalah yang membuka halaman ini paling sering, dari HP, lewat
         * WiFi venue.
         */
        return view('dashboard', [
            'turnamen' => $turnamen,
            'penugasan' => $penugasan['daftar'],
            'penugasanSisa' => $penugasan['sisa'],
            // Ringkasan kejuaraan hanya berarti bagi yang mengurusnya. Wasit dan
            // juri tidak punya urusan dengan jumlah pendaftaran, dan
            // menampilkannya membuat halaman depan mereka terasa salah alamat.
            'tampilkanRingkasan' => $ringkasan,
            'pekerjaan' => $ringkasan ? $this->pekerjaan->untuk($turnamen) : [],
            'gelanggangSaya' => $turnamen ? $this->gelanggangSaya($turnamen) : [],
            'antrean' => $ringkasan && $turnamen ? $this->antreanGelanggang($turnamen) : [],
            'hasilTerakhir' => $ringkasan && $turnamen ? $this->hasilTerakhir($turnamen) : [],
        ]);
    }

    /**
     * Petugas satu gelanggang mendarat langsung di panelnya.
     *
     * Dashboard tidak berarti apa-apa bagi juri yang duduk di gelanggang yang
     * sama sepanjang hari: satu-satunya hal yang dicarinya di sana adalah
     * tautan ke panelnya sendiri. Menghapus langkah itu berarti menghapus
     * seluruh navigasi dari pekerjaannya -- ia login, dan tombol nilai sudah
     * ada di depannya.
     *
     * Yang bertugas di LEBIH DARI SATU gelanggang tidak dialihkan: sistem
     * tidak punya dasar memilih salah satunya, dan menebak berarti
     * mendaratkannya di gelanggang yang keliru.
     *
     * Pengalihan ini tidak pernah mengunci. `?dashboard=1` melewatinya, dan
     * panel menyediakan tautannya -- petugas yang juga memegang peran lain
     * tidak boleh terperangkap di satu layar.
     */
    private function alihkanKePanelGelanggang(?Tournament $turnamen): ?RedirectResponse
    {
        if ($turnamen === null || request()->boolean('dashboard')) {
            return null;
        }

        /*
         * Seluruh peran yang bertugas DI gelanggang, bukan hanya juri dan
         * wasit. Yang duduk di kursi Dewan Wasit Juri, Komisi Protes, atau
         * Ketua Pertandingan sepanjang hari juga tidak punya alasan melewati
         * dashboard lebih dulu -- dan panelnya sama-sama mengikuti partai
         * aktif gelanggangnya.
         *
         * Pengendali Gelanggang dan Operator IT sengaja TIDAK di sini:
         * pekerjaan mereka justru mengurus perpindahan, jadi mereka butuh
         * layar yang memandang lebih dari satu partai.
         */
        $panel = [
            MatchOfficial::ROLE_JURI => 'juri',
            MatchOfficial::ROLE_WASIT => 'wasit',
            'dewan-juri' => 'dewan-juri',
            'komisi-protes' => 'komisi-protes',
            'ketua-pertandingan' => 'ketua',
        ];

        $tugas = ArenaOfficial::query()
            ->where('user_id', auth()->id())
            ->whereIn('role', array_keys($panel))
            ->whereHas('arena', fn ($q) => $q->where('tournament_id', $turnamen->id)->where('is_active', true))
            ->with('arena')
            ->get();

        if ($tugas->count() !== 1) {
            return null;
        }

        /*
         * Penugasan per PARTAI ikut dihitung, bukan hanya per gelanggang.
         *
         * Kedua tabel bisa berbeda pendapat: `arena_officials` menempatkan
         * seorang juri di Gelanggang A sepanjang hari, sementara
         * `match_officials` masih memegangnya sebagai aparat pada satu partai
         * di Gelanggang B -- sisa penugasan lama, atau penambalan menit
         * terakhir yang tidak ikut tercatat di tingkat gelanggang.
         *
         * Selama hanya `arena_officials` yang dibaca, juri itu didaratkan
         * diam-diam di Gelanggang A padahal ia dipanggil ke B, dan nilainya
         * masuk ke partai yang salah tanpa satu pun isyarat di layarnya.
         *
         * Yang dihitung hanya partai yang SEDANG hidup: berstatus berlangsung,
         * atau sedang ditayangkan gelanggangnya. Seluruh partai terjadwal ikut
         * dihitung berarti hampir setiap juri terlempar ke dashboard sepanjang
         * hari -- bagan besar menyebar nama yang sama ke dua gelanggang untuk
         * partai yang baru dimainkan sore nanti, dan itu bukan kebingungan
         * yang perlu dijawab sekarang.
         */
        $satu = $tugas->first();

        $sedangTayang = collect(Arena::partaiYangSedangTayang($turnamen->id));

        $gelanggangHidup = MatchOfficial::query()
            ->where('user_id', auth()->id())
            ->whereHas('match', fn ($q) => $q
                ->whereNotNull('arena_id')
                ->where(fn ($w) => $w
                    ->where('status', SilatMatch::STATUS_BERLANGSUNG)
                    ->orWhereIn('id', $sedangTayang))
                ->whereHas('arena', fn ($a) => $a->where('tournament_id', $turnamen->id)))
            ->with('match:id,arena_id')
            ->get()
            ->pluck('match.arena_id');

        if ($gelanggangHidup->push($satu->arena_id)->unique()->count() > 1) {
            return null;
        }

        /*
         * Jangan daratkan orang di panel yang akan membalas 403.
         *
         * Panel juri dan wasit dijaga penugasan PER PARTAI, sementara
         * pendaratan ini membaca penugasan per gelanggang. Kedua tabel bisa
         * berbeda: aparat gelanggang disalin ke partai hanya saat pengendali
         * menunjuknya, dan penyalinan itu sengaja tidak menimpa aparat yang
         * sudah ditugaskan khusus untuk partai tersebut. Wasit yang memegang
         * Gelanggang B karena itu bisa mendarat di panel wasit yang partai
         * aktifnya dipegang orang lain -- dan yang dilihatnya adalah halaman
         * "Anda tidak ditugaskan sebagai aparat pada partai ini", bukan
         * tombolnya.
         *
         * Dashboard jauh lebih berguna daripada 403: dari sana ia melihat
         * kartu partai yang memang miliknya.
         */
        $dijagaPartai = in_array($satu->role, [MatchOfficial::ROLE_JURI, MatchOfficial::ROLE_WASIT], true);
        $partaiTayang = $satu->arena->active_match_id;

        if ($dijagaPartai && $partaiTayang !== null) {
            $ikutBertugas = MatchOfficial::query()
                ->where('user_id', auth()->id())
                ->where('match_id', $partaiTayang)
                ->exists();

            if (! $ikutBertugas) {
                return null;
            }
        }

        return redirect()->route(
            "admin.turnamen.gelanggang.panel.{$panel[$satu->role]}",
            [$turnamen, $satu->arena],
        );
    }

    /**
     * Antrean tiap gelanggang: partai yang sudah ditempatkan dan belum selesai.
     *
     * Menggantikan grafik "Pengguna baru 6 bulan terakhir" yang tidak pernah
     * berarti apa-apa bagi panitia dan menyeret 843 kB apexcharts ke dalam
     * bundel admin demi satu batang. Daftar ini dirender HTML biasa.
     *
     * Batasnya gelanggang, bukan tanggal. Jadwal tidak lagi menyimpan jam,
     * jadi "hari ini" tidak punya arti yang bisa dihitung -- yang berarti
     * bagi panitia adalah apa yang masih mengantre di depannya.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function antreanGelanggang(Tournament $turnamen): array
    {
        return SilatMatch::whereHas('bracket', fn ($q) => $q->whereIn('weight_class_id', $turnamen->weightClasses()->select('id')))
            ->whereNotNull('arena_id')
            ->where('status', '!=', SilatMatch::STATUS_SELESAI)
            ->with(['arena', 'bracket.weightClass', 'red.athletes', 'blue.athletes'])
            ->orderBy('order_in_arena')
            ->get()
            ->groupBy(fn (SilatMatch $m) => $m->arena?->name ?? 'Belum ditempatkan')
            ->map(fn ($partai) => $partai->map(fn (SilatMatch $m) => [
                'kelas' => $m->bracket->weightClass->name,
                'merah' => $m->red?->athletes->pluck('name')->implode(', '),
                'biru' => $m->blue?->athletes->pluck('name')->implode(', '),
                'urutan' => $m->order_in_arena,
                'berlangsung' => $m->status === SilatMatch::STATUS_BERLANGSUNG,
            ])->values()->all())
            ->all();
    }

    /**
     * Hasil partai terakhir yang sudah disahkan dewan juri.
     *
     * Yang belum disahkan sengaja tidak ikut: sebelum pengesahan, pemenangnya
     * masih bisa berubah, dan halaman depan bukan tempat yang tepat untuk
     * menyiarkan angka yang belum final.
     *
     * @return array<int, array<string, string>>
     */
    private function hasilTerakhir(Tournament $turnamen): array
    {
        return SilatMatch::whereHas('bracket', fn ($q) => $q->whereIn('weight_class_id', $turnamen->weightClasses()->select('id')))
            ->whereNotNull('ratified_at')
            ->with(['bracket.weightClass', 'red.athletes', 'blue.athletes'])
            ->latest('ratified_at')
            ->take(6)
            ->get()
            ->map(function (SilatMatch $m) {
                $menang = $m->winner_registration_id === $m->red_registration_id ? $m->red : $m->blue;

                return [
                    'teks' => trim(($menang?->athletes->pluck('name')->implode(', ') ?: 'Pemenang').' — '
                        .(AlasanMenang::label($m->win_reason) ?? 'Sah').' · '.$m->bracket->weightClass->name),
                    'waktu' => $m->ratified_at?->diffForHumans() ?? '',
                ];
            })
            ->all();
    }

    /**
     * Partai tempat pengguna yang sedang login ditugaskan sebagai wasit atau juri.
     *
     * Tanpa ini, wasit dan juri yang baru login berhenti di halaman kosong:
     * panel mereka terikat ke satu partai (`/partai/{match}/juri`), dan sidebar
     * hanya bisa membentuk alamat berparameter kejuaraan — tidak ada satu
     * alamat "panel juri" yang benar tanpa tahu partai mana. Selama ini
     * alamatnya dibagikan Operator IT lewat pesan; kartu ini membuat mereka
     * menemukannya sendiri.
     *
     * Kategori Jurus tidak ikut: penampilan Jurus tidak punya penugasan per
     * orang, siapa pun berperan juri boleh menilai, dan pintu masuknya sudah
     * berdiri sebagai menu tersendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Gelanggang yang dipegang orang ini, beserta pintu ke panelnya.
     *
     * Pengendali Gelanggang dan Operator IT sengaja TIDAK dialihkan otomatis
     * (lihat alihkanKePanelGelanggang) karena pekerjaan mereka mengurus
     * perpindahan dan butuh layar yang memandang lebih dari satu partai.
     * Tanpa tautan di sini, konsekuensinya bukan "melihat dashboard dulu"
     * melainkan tidak punya jalan sama sekali: nama rute panel kendali tidak
     * dirujuk di mana pun, dan kartu "Partai saya" hanya menyusun alamat
     * juri/wasit dari `match_officials` -- tabel yang tidak pernah menyebut
     * kedua peran ini. Panel yang tidak punya pintu masuk sama saja tidak ada.
     *
     * Alamatnya per GELANGGANG, bukan per partai, dengan alasan yang sama
     * seperti kartu "Partai saya": alamat partai basi begitu jadwal berpindah.
     *
     * @return array<int, array{nama: string, sebutan: string, aksi: string, url: string}>
     */
    private function gelanggangSaya(Tournament $turnamen): array
    {
        $kursi = [
            ['pengendali', 'Pengendali Gelanggang', 'kendali', 'Buka panel kendali'],
            ['operators', 'Operator IT', 'papan', 'Buka papan tampilan'],
        ];

        $daftar = [];

        foreach ($kursi as [$relasi, $sebutan, $rute, $aksi]) {
            $gelanggang = Arena::query()
                ->where('tournament_id', $turnamen->id)
                ->aktif()
                ->whereHas($relasi, fn ($q) => $q->whereKey(auth()->id()))
                ->orderBy('sort_order')
                ->get();

            foreach ($gelanggang as $arena) {
                $daftar[] = [
                    'nama' => $arena->name,
                    'sebutan' => $sebutan,
                    'aksi' => $aksi,
                    'url' => route("admin.turnamen.gelanggang.panel.{$rute}", [$turnamen, $arena]),
                ];
            }
        }

        return $daftar;
    }

    /**
     * Penugasan yang ditampilkan sebagai kartu, beserta sisa yang tidak muat.
     *
     * Dua batas, dan keduanya lahir dari pengukuran di basis data lapangan.
     *
     * KEJUARAAN AKTIF saja. Sebelumnya kartu ini membaca seluruh penugasan yang
     * belum selesai, lintas kejuaraan -- satu akun juri di mesin lapangan
     * memegang 388 penugasan yang tersebar di tiga kejuaraan sekaligus, dan
     * yang dua di antaranya bukan kejuaraan yang sedang dibukanya. Sisa
     * dashboard sudah lama terikat kejuaraan aktif; kartu ini tertinggal.
     *
     * JUMLAHNYA DIBATASI. Bahkan sesudah disaring, kejuaraan besar meninggalkan
     * 282 penugasan untuk satu juri -- beranda 822 KB yang butuh 1,08 detik,
     * dan yang membukanya juri dari HP lewat WiFi venue. Aparat tidak sedang
     * membaca seluruh jadwalnya di sini; ia mencari partai berikutnya.
     *
     * Sisanya DISEBUT, tidak dihilangkan diam-diam: aparat yang tahu ia
     * dijadwalkan lebih banyak tidak boleh menyimpulkan jadwalnya berkurang.
     *
     * @return array{daftar: array<int, array<string, mixed>>, sisa: int}
     */
    private function penugasanSaya(?Tournament $turnamen): array
    {
        if ($turnamen === null) {
            return ['daftar' => [], 'sisa' => 0];
        }

        $dasar = MatchOfficial::query()
            ->where('user_id', auth()->id())
            ->whereHas('match', fn ($query) => $query
                ->where('status', '!=', SilatMatch::STATUS_SELESAI)
                ->whereHas('bracket.weightClass', fn ($kelas) => $kelas->where('tournament_id', $turnamen->id)));

        /*
         * Dihitung lewat COUNT, bukan dengan mengambil semuanya lalu menghitung
         * barisnya: yang dicari cuma satu angka, dan mengambil 282 baris
         * beserta enam relasinya untuk itu adalah persis beban yang sedang
         * dihilangkan.
         */
        $jumlah = (clone $dasar)->count();

        $penugasan = $dasar
            ->with([
                'match.arena',
                'match.red.athletes',
                'match.red.contingent',
                'match.blue.athletes',
                'match.blue.contingent',
                'match.bracket.weightClass.tournament',
            ])
            ->limit(self::MAKS_PENUGASAN)
            ->get();

        $daftar = $penugasan
            ->sortBy(fn (MatchOfficial $tugas) => $tugas->match->order_in_arena ?? PHP_INT_MAX)
            ->map(function (MatchOfficial $tugas): array {
                $match = $tugas->match;
                $tournament = $match->bracket->weightClass->tournament;

                return [
                    'sebutan' => $tugas->sebutan(),
                    'kelas' => $match->bracket->weightClass->name,
                    'kejuaraan' => $tournament->name,
                    'gelanggang' => $match->arena?->name,
                    'urutan' => $match->order_in_arena,
                    'merah' => $match->red?->athletes->pluck('name')->implode(', '),
                    'merah_kontingen' => $match->red?->contingent->name,
                    'biru' => $match->blue?->athletes->pluck('name')->implode(', '),
                    'biru_kontingen' => $match->blue?->contingent->name,
                    'berlangsung' => $match->status === SilatMatch::STATUS_BERLANGSUNG,
                    /*
                     * Menunjuk panel GELANGGANG kalau partainya sudah
                     * dijadwalkan.
                     *
                     * Alamat per-partai basi begitu pengendali memindahkan
                     * jadwal, dan petugas yang menekan kartu lama mendarat di
                     * partai yang sudah lewat. Alamat gelanggang tidak pernah
                     * basi: ia mengikuti apa pun yang sedang ditayangkan.
                     *
                     * Partai yang belum punya gelanggang tetap memakai alamat
                     * lama -- tidak ada gelanggang untuk diikuti.
                     */
                    'url' => $match->arena !== null
                        ? route(
                            $tugas->role === MatchOfficial::ROLE_WASIT
                                ? 'admin.turnamen.gelanggang.panel.wasit'
                                : 'admin.turnamen.gelanggang.panel.juri',
                            [$tournament, $match->arena],
                        )
                        : route(
                            $tugas->role === MatchOfficial::ROLE_WASIT
                                ? 'admin.turnamen.partai.wasit'
                                : 'admin.turnamen.partai.juri',
                            [$tournament, $match],
                        ),
                ];
            })
            ->values()
            ->all();

        return ['daftar' => $daftar, 'sisa' => max(0, $jumlah - count($daftar))];
    }
}
