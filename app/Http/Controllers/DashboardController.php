<?php

namespace App\Http\Controllers;

use App\Enums\ResourceAction;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Beranda\PekerjaanMenunggu;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Scoring\AlasanMenang;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NavigationBuilder $navigasi,
        private readonly PekerjaanMenunggu $pekerjaan,
    ) {}

    public function __invoke(): View
    {
        $turnamen = $this->navigasi->turnamenAktif();

        return view('dashboard', [
            'turnamen' => $turnamen,
            'penugasan' => $this->penugasanSaya(),
            // Ringkasan kejuaraan hanya berarti bagi yang mengurusnya. Wasit dan
            // juri tidak punya urusan dengan jumlah pendaftaran, dan
            // menampilkannya membuat halaman depan mereka terasa salah alamat.
            'tampilkanRingkasan' => resource_allows(rk('turnamen', ResourceAction::View)),
            'pekerjaan' => $this->pekerjaan->untuk($turnamen),
            'partaiHariIni' => $turnamen ? $this->partaiHariIni($turnamen) : [],
            'hasilTerakhir' => $turnamen ? $this->hasilTerakhir($turnamen) : [],
        ]);
    }

    /**
     * Jadwal hari ini per gelanggang.
     *
     * Menggantikan grafik "Pengguna baru 6 bulan terakhir" yang tidak pernah
     * berarti apa-apa bagi panitia dan menyeret 843 kB apexcharts ke dalam
     * bundel admin demi satu batang. Daftar ini dirender HTML biasa.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function partaiHariIni(Tournament $turnamen): array
    {
        return SilatMatch::whereHas('bracket', fn ($q) => $q->whereIn('weight_class_id', $turnamen->weightClasses()->select('id')))
            ->whereDate('scheduled_at', today())
            ->with(['arena', 'bracket.weightClass', 'red.athletes', 'blue.athletes'])
            ->orderBy('order_in_arena')
            ->get()
            ->groupBy(fn (SilatMatch $m) => $m->arena?->name ?? 'Belum ditempatkan')
            ->map(fn ($partai) => $partai->map(fn (SilatMatch $m) => [
                'kelas' => $m->bracket->weightClass->name,
                'merah' => $m->red?->athletes->pluck('name')->implode(', '),
                'biru' => $m->blue?->athletes->pluck('name')->implode(', '),
                'waktu' => $m->scheduled_at?->format('H:i'),
                'berlangsung' => $m->status === SilatMatch::STATUS_BERLANGSUNG,
                'selesai' => $m->status === SilatMatch::STATUS_SELESAI,
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
            ->sortBy(fn (MatchOfficial $tugas) => $tugas->match->scheduled_at ?? now()->addCentury())
            ->map(function (MatchOfficial $tugas): array {
                $match = $tugas->match;
                $tournament = $match->bracket->weightClass->tournament;

                return [
                    'sebutan' => $tugas->sebutan(),
                    'kelas' => $match->bracket->weightClass->name,
                    'kejuaraan' => $tournament->name,
                    'gelanggang' => $match->arena?->name,
                    'waktu' => $match->scheduled_at?->translatedFormat('D, d M H:i'),
                    'merah' => $match->red?->athletes->pluck('name')->implode(', '),
                    'merah_kontingen' => $match->red?->contingent->name,
                    'biru' => $match->blue?->athletes->pluck('name')->implode(', '),
                    'biru_kontingen' => $match->blue?->contingent->name,
                    'berlangsung' => $match->status === SilatMatch::STATUS_BERLANGSUNG,
                    'url' => route(
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
