<?php

namespace App\Http\Controllers\Public;

use App\Enums\Sudut;
use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\Bracket;
use App\Models\JurusEvent;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Jurus\JurusScoreCalculator;
use App\Support\Live\StatePartaiPublik;
use App\Support\Rekap\RekapMedali;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Live score publik -- FR-H. Dibuka tanpa login lewat tunnel ke internet,
 * jadi payloadnya memakai App\Support\Live\StatePartaiPublik yang sama
 * dipakai overlay siaran: tanpa `officials`, tanpa input mentah juri.
 *
 * Beda dari overlay, halaman di sini punya rangka visual (header, footer,
 * navigasi) karena memang ditonton langsung, bukan dikomposit vMix di atas
 * kamera -- dan endpoint state()-nya dicache sebentar supaya lonjakan
 * penonton yang menyambung ulang bersamaan (lihat FR-H-05) tidak memukul
 * database tiap milidetik.
 */
class LiveScoreController extends Controller
{
    public function __construct(
        private readonly StatePartaiPublik $state,
        private readonly JurusScoreCalculator $jurusKalkulator,
        private readonly TandingScoreCalculator $tandingKalkulator,
        private readonly RekapMedali $rekap,
    ) {}

    public function gelanggang(Arena $arena): View
    {
        return view('public.live.gelanggang', [
            'arena' => $arena->load('tournament'),
            'config' => [
                'arenaId' => $arena->id,
                'state' => route('live.gelanggang.state', $arena),
            ],
        ]);
    }

    public function state(Arena $arena): JsonResponse
    {
        $data = Cache::remember("live-state-arena-{$arena->id}", 1, fn () => ($this->state)($arena));

        return response()->json($data);
    }

    public function turnamen(Tournament $tournament): View
    {
        /*
         * Gelanggang dibawa BESERTA partai yang sedang berjalan di dalamnya.
         *
         * Sebelumnya bagian ini cuma daftar nama gelanggang, sehingga orang
         * yang membuka halaman di tengah acara harus menekan satu per satu
         * untuk tahu mana yang sedang jalan. Pertanyaan pertamanya justru
         * "berapa sekarang", bukan "gelanggang apa saja yang ada".
         */
        $arenas = $tournament->arenas()
            ->with(['matches' => fn ($q) => $q
                ->where('status', SilatMatch::STATUS_BERLANGSUNG)
                ->with(['bracket.weightClass:id,name', 'red.athletes:id,name', 'blue.athletes:id,name'])
                ->limit(1),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Arena $arena) {
                $partai = $arena->matches->first();

                return [
                    'arena' => $arena,
                    'partai' => $partai,
                    'skor' => $partai ? [
                        'merah' => $this->tandingKalkulator->skor($partai, Sudut::Merah),
                        'biru' => $this->tandingKalkulator->skor($partai, Sudut::Biru),
                    ] : null,
                ];
            });

        /*
         * Kelas dikelompokkan per golongan usia. Satu kejuaraan daerah bisa
         * punya seratus lebih kelas, dan daftar datar sepanjang itu memaksa
         * orang menggulir mencari golongan anaknya sendiri. Golongan usia
         * adalah cara orang tua dan official menyebut kelas di lapangan.
         */
        /*
         * Yang ditampilkan hanya kelas yang benar-benar dipertandingkan.
         *
         * Katalog naskah 2025 berisi 174 kelas dan satu kejuaraan menjalankan
         * sebagian kecilnya, jadi tanpa saringan ini halaman penonton berisi
         * ratusan baris "Bagan belum tersusun" yang mengubur kelas yang
         * sungguh berjalan -- di jaringan gelanggang, lewat ponsel.
         *
         * Kelas yang sudah punya bagan tetap ikut walau pendaftarannya
         * kemudian dicabut: bagannya sudah terlanjur tersusun dan hasilnya
         * bagian dari kejuaraan ini.
         */
        $kelas = $tournament->weightClasses()
            ->where(fn ($q) => $q->has('registrations')->orHas('bracket'))
            ->with(['bracket' => fn ($q) => $q->with('matches.winner.athletes')])
            ->urutGolonganUsia()->orderBy('jenis_kelamin')->orderBy('code')
            ->get()
            ->map(function (WeightClass $wc) {
                $final = $wc->bracket?->matches->sortByDesc('round')->first();
                $juara = $final && $final->disahkan() ? $final->winner?->athletes->pluck('name')->implode(', ') : null;

                return ['kelas' => $wc, 'punya_bagan' => $wc->bracket !== null, 'juara' => $juara];
            })
            ->groupBy(fn (array $baris) => $baris['kelas']->golongan_usia->label());

        /*
         * Skor akhir dihitung di sini, bukan di dalam perulangan Blade.
         * View sebelumnya memanggil app(JurusScoreCalculator::class) sekali
         * per baris peringkat, padahal kalkulatornya sudah disuntik ke
         * controller ini.
         */
        $jurusEvents = $tournament->jurusEvents()->aktif()
            ->with(['performances' => fn ($q) => $q->whereNotNull('ratified_at')->with('registration.athletes', 'registration.contingent', 'scores')])
            ->urutGolonganUsia()->orderBy('sort_order')
            ->get()
            ->filter(fn (JurusEvent $e) => $e->performances->isNotEmpty())
            ->map(fn (JurusEvent $e) => [
                'nomor' => $e,
                'peringkat' => $this->jurusKalkulator->peringkat($e->performances)->values()
                    ->map(fn ($penampilan) => [
                        'penampilan' => $penampilan,
                        'skor' => $penampilan->didiskualifikasi ? null : $this->jurusKalkulator->skorAkhir($penampilan),
                    ]),
            ]);

        return view('public.live.turnamen', [
            'tournament' => $tournament,
            'arenas' => $arenas,
            'kelas' => $kelas,
            'jurusEvents' => $jurusEvents,
        ]);
    }

    public function medali(Tournament $tournament): View
    {
        return view('public.live.medali', [
            'tournament' => $tournament,
            'peringkatUmum' => $this->rekap->peringkatUmum($tournament),
            'tanding' => $this->rekap->tanding($tournament),
            'jurus' => $this->rekap->jurus($tournament),
        ]);
    }

    public function bagan(Tournament $tournament, WeightClass $weightClass): View
    {
        abort_unless($weightClass->tournament_id === $tournament->id, 404);

        $bracket = Bracket::where('weight_class_id', $weightClass->id)->with([
            'matches.red.athletes', 'matches.red.contingent',
            'matches.blue.athletes', 'matches.blue.contingent',
        ])->first();

        return view('public.live.bagan', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'babak' => $bracket?->matches->groupBy('round'),
        ]);
    }
}
