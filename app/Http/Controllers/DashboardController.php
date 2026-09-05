<?php

namespace App\Http\Controllers;

use App\Enums\ResourceAction;
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

        return view('dashboard', [
            'turnamen' => $turnamen,
            'penugasan' => $this->penugasanSaya(),
            // Ringkasan kejuaraan hanya berarti bagi yang mengurusnya. Wasit dan
            // juri tidak punya urusan dengan jumlah pendaftaran, dan
            // menampilkannya membuat halaman depan mereka terasa salah alamat.
            'tampilkanRingkasan' => resource_allows(rk('turnamen', ResourceAction::View)),
            'pekerjaan' => $this->pekerjaan->untuk($turnamen),
            'antrean' => $turnamen ? $this->antreanGelanggang($turnamen) : [],
            'hasilTerakhir' => $turnamen ? $this->hasilTerakhir($turnamen) : [],
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

        $satu = $tugas->first();

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
    private function penugasanSaya(): array
    {
        $penugasan = MatchOfficial::query()
            ->where('user_id', auth()->id())
            ->whereHas('match', fn ($query) => $query->where('status', '!=', SilatMatch::STATUS_SELESAI))
            ->with([
                'match.arena',
                'match.red.athletes',
                'match.red.contingent',
                'match.blue.athletes',
                'match.blue.contingent',
                'match.bracket.weightClass.tournament',
            ])
            ->get();

        return $penugasan
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
    }
}
