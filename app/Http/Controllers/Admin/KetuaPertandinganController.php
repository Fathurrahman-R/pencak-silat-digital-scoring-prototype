<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Sudut;
use App\Http\Controllers\Controller;
use App\Models\Arena;
use App\Models\JudgeVerification;
use App\Models\JurusPerformance;
use App\Models\ManagerProtest;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Models\VarReview;
use App\Support\Scoring\PollingVerifikasi;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Panel Ketua Pertandingan -- Pasal 13.4.
 *
 * Peran `ketua-pertandingan` sudah ada di SilatRoleSeeder sejak awal, dengan
 * izin lengkap dan deskripsi yang berbunyi "memimpin verifikasi juri", tapi
 * tidak punya satu pun layar. Yang memegang jabatan itu selama ini harus
 * membuka panel gelanggang satu per satu untuk tahu apa yang sedang terjadi.
 *
 * # Kenapa panel ini bukan panel partai
 *
 * Semua panel gelanggang lain menjawab "apa yang terjadi di partai ini".
 * Panel ini menjawab pertanyaan yang berbeda: "apa yang menghambat kelancaran
 * gelanggang sekarang". Ia melihat SELURUH gelanggang sekaligus, dan yang
 * dibawanya ke depan bukan skor melainkan perkara yang menunggu keputusan --
 * masing-masing dengan tenggatnya sendiri.
 *
 * Karena itu ia tidak memakai partaiPanel: tidak ada satu partai yang jadi
 * pusatnya, dan tidak ada timer yang dikendalikannya sendiri.
 */
class KetuaPertandinganController extends Controller
{
    public function __construct(
        private readonly TandingScoreCalculator $kalkulator,
        private readonly PollingVerifikasi $polling,
    ) {}

    public function index(Tournament $tournament): View
    {
        return view('silat.ketua-pertandingan', [
            'tournament' => $tournament,
            'config' => [
                'state' => route('admin.turnamen.ketua-pertandingan.state', $tournament),
                /*
                 * Alamat aksi dibuat per gelanggang di klien dengan menukar
                 * __MATCH__ -- panel ini tidak tahu partai mana yang berjalan
                 * saat halamannya digambar, dan partai itu berganti sepanjang
                 * hari tanpa halaman dimuat ulang.
                 */
                'verifikasiMinta' => route('admin.turnamen.partai.verifikasi.minta', [$tournament, '__MATCH__']),
                'timerJeda' => route('admin.turnamen.partai.timer.jeda', [$tournament, '__MATCH__']),
                'partaiWasit' => route('admin.turnamen.partai.wasit', [$tournament, '__MATCH__']),
                'partaiKeberatan' => route('admin.turnamen.partai.keberatan', [$tournament, '__MATCH__']),
            ],
        ]);
    }

    /**
     * Keadaan seluruh gelanggang, disegarkan berkala oleh panel.
     *
     * Tidak lewat Reverb. Panel ini memantau banyak gelanggang sekaligus,
     * jadi ia harus berlangganan semua channel gelanggang -- dan channel
     * gelanggang adalah presence channel yang keanggotaannya dipakai panel
     * lain untuk menghitung siapa yang tersambung. Ketua Pertandingan yang
     * ikut masuk ke enam channel sekaligus akan muncul sebagai "petugas
     * tersambung" di enam gelanggang yang tidak ditungguinya.
     */
    public function state(Tournament $tournament): JsonResponse
    {
        return response()->json([
            'gelanggang' => $this->gelanggang($tournament),
            'antrean' => $this->antrean($tournament),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function gelanggang(Tournament $tournament): Collection
    {
        return $tournament->arenas()->orderBy('name')->get()->map(function (Arena $arena) {
            /*
             * Pointer gelanggang lebih dulu, turunan status sebagai jaring
             * pengaman -- pola yang sama dengan StatePartaiPublik. Panel ini
             * memantau seluruh kejuaraan sekaligus, jadi gelanggang yang
             * pointernya belum terisi tidak boleh tampil kosong seolah tidak
             * ada yang berjalan di sana.
             */
            $muatan = [
                /*
                 * `tournament_id` wajib ikut dipilih. Tanpa kolom itu, relasi
                 * `tournament` di bawahnya selalu null dan pemanggilan
                 * peraturan() melempar galat -- pembatasan kolom pada eager
                 * load memotong kunci asing yang justru dipakai relasi
                 * berikutnya.
                 */
                'bracket.weightClass:id,tournament_id,name,jenis_kelamin,golongan_usia',
                'bracket.weightClass.tournament',
                'red.athletes:id,name', 'blue.athletes:id,name',
                'officials.user:id,name', 'rounds',
            ];

            $partai = SilatMatch::query()
                ->when(
                    $arena->active_match_id !== null,
                    fn ($q) => $q->whereKey($arena->active_match_id),
                    fn ($q) => $q->where('status', SilatMatch::STATUS_BERLANGSUNG),
                )
                ->where('arena_id', $arena->id)
                ->with($muatan)
                ->first();

            $jurus = JurusPerformance::query()
                ->where('arena_id', $arena->id)
                ->where('status', JurusPerformance::STATUS_BERLANGSUNG)
                ->with(['jurusEvent', 'registration.athletes:id,name'])
                ->first();

            return [
                'arena' => ['id' => $arena->id, 'name' => $arena->name],
                'tanding' => $partai ? $this->ringkasanPartai($partai) : null,
                'jurus' => $jurus ? $this->ringkasanJurus($jurus) : null,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function ringkasanPartai(SilatMatch $match): array
    {
        $babak = $match->rounds->firstWhere('round', $match->current_round);

        $aparat = $match->officials->groupBy('role')->map(
            fn ($kelompok) => $kelompok->sortBy('number')->map(fn ($o) => $o->user?->name)->filter()->values()
        );

        $verifikasi = JudgeVerification::query()
            ->where('match_id', $match->id)
            ->berjalan()
            ->with('answers:id,judge_verification_id')
            ->first();

        return [
            'id' => $match->id,
            'kelas' => $match->bracket->weightClass->jenis_kelamin->label().' '
                .$match->bracket->weightClass->golongan_usia->label().' — '
                .$match->bracket->weightClass->name,
            'babak' => $match->current_round,
            'jumlah_babak' => $match->bracket->weightClass->tournament->peraturan()
                ->babakUntuk($match->bracket->weightClass->golongan_usia)['jumlah'],
            'status_babak' => $babak?->status->value,
            'sisa_ms' => $babak?->sisaMs(),
            'merah' => [
                'nama' => $match->red?->athletes->pluck('name')->implode(', '),
                'skor' => $this->kalkulator->skor($match, Sudut::Merah),
            ],
            'biru' => [
                'nama' => $match->blue?->athletes->pluck('name')->implode(', '),
                'skor' => $this->kalkulator->skor($match, Sudut::Biru),
            ],
            'aparat' => [
                'wasit' => $aparat->get('wasit', collect())->implode(', ') ?: null,
                'juri' => $aparat->get('juri', collect())->implode(' · ') ?: null,
            ],
            'verifikasi' => $verifikasi ? [
                'id' => $verifikasi->id,
                'pertanyaan' => $verifikasi->jenis->pertanyaan(),
                'terjawab' => $verifikasi->answers->count(),
                'jumlah_juri' => $this->polling->jumlahJuri($verifikasi),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function ringkasanJurus(JurusPerformance $penampilan): array
    {
        /*
         * Waktu penampilan Jurus adalah tanggung jawab Ketua Pertandingan --
         * Pasal 13.4.d.9. Karena itu waktu acuan dan toleransinya dibawa ke
         * sini, bukan cuma jam berjalannya: yang harus diputuskan bukan
         * "sudah berapa lama", melainkan "sudah lewat toleransi atau belum".
         */
        $golongan = $penampilan->jurusEvent->golongan_usia;

        /*
         * Toleransinya BERBEDA per golongan usia -- Pasal 12.1.e.1.a memberi
         * 10 detik untuk Usia Dini dan Pra Remaja, 5 detik untuk Remaja dan
         * Dewasa. Satu angka tetap akan membuat panel ini menyatakan penampilan
         * anak-anak lewat batas padahal masih di dalam toleransinya.
         */
        $toleransiDetik = $penampilan->jurusEvent->tournament
            ->peraturan()
            ->jurusWaktuUntuk($golongan)['toleransi_detik'];

        $acuanMs = $penampilan->jurusEvent->waktu_acuan_ms;
        $toleransiMs = $toleransiDetik * 1000;

        return [
            'id' => $penampilan->id,
            'nomor' => $penampilan->jurusEvent->nama(),
            'pesilat' => $penampilan->registration->athletes->pluck('name')->implode(', '),
            'durasi_ms' => $penampilan->duration_ms,
            'acuan_ms' => $acuanMs,
            'toleransi_ms' => $toleransiMs,
        ];
    }

    /**
     * Perkara yang menunggu keputusan Ketua Pertandingan, paling mendesak dulu.
     *
     * Urutannya bukan kronologis melainkan menurut TENGGAT: yang tenggatnya
     * sudah lewat naik ke atas. Daftar kronologis akan menaruh perkara yang
     * baru masuk di atas perkara yang sudah lewat batas waktunya, dan justru
     * yang terakhir itu yang menghentikan gelanggang.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function antrean(Tournament $tournament): Collection
    {
        $idPartai = SilatMatch::query()
            ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->pluck('id');

        $protes = ManagerProtest::query()
            ->whereIn('match_id', $idPartai)
            ->whereNull('diputuskan_at')
            ->whereNotNull('formulir_dikembalikan_at')
            ->where('level', ManagerProtest::TINGKAT_PERTAMA)
            ->with('match.bracket.weightClass')
            ->get()
            ->map(fn (ManagerProtest $p) => [
                'jenis' => 'protes-manajer',
                'judul' => 'Protes Manajer tingkat pertama',
                'partai_id' => $p->match_id,
                'keterangan' => "Partai {$p->match_id}. Formulir sudah dikembalikan, menunggu keputusanmu.",
                'tenggat_at' => $p->tenggat_keputusan_at?->toIso8601String(),
                'lewat' => $p->tenggat_keputusan_at?->isPast() ?? false,
            ]);

        $var = VarReview::query()
            ->whereIn('match_id', $idPartai)
            ->whereNull('diputuskan_at')
            ->whereNotNull('tenggat_at')
            ->get()
            ->filter(fn (VarReview $v) => $v->tenggat_at->isPast())
            ->map(fn (VarReview $v) => [
                'jenis' => 'var-lewat-tenggat',
                'judul' => 'Protes VAR lewat tenggat',
                'partai_id' => $v->match_id,
                'keterangan' => "Partai {$v->match_id}. Wasit Komisi Protes belum memutus. "
                    .'Pasal 15 menyerahkan kelanjutannya ke verifikasi juri yang kamu pimpin.',
                'tenggat_at' => $v->tenggat_at->toIso8601String(),
                'lewat' => true,
            ]);

        $verifikasi = JudgeVerification::query()
            ->whereIn('match_id', $idPartai)
            ->berjalan()
            ->with('answers:id,judge_verification_id')
            ->get()
            ->map(fn (JudgeVerification $v) => [
                'jenis' => 'verifikasi',
                'judul' => 'Verifikasi juri berjalan',
                'partai_id' => $v->match_id,
                'keterangan' => "Partai {$v->match_id} · \"{$v->jenis->pertanyaan()}\"",
                'jumlah' => $v->answers->count().' / '.$this->polling->jumlahJuri($v),
                'tenggat_at' => null,
                // Verifikasi tidak punya tenggat -- pertandingan memang berhenti
                // sampai juri menjawab. Ia tetap masuk antrean karena itu justru
                // hal yang sedang menahan gelanggang.
                'lewat' => false,
            ]);

        return $protes->concat($var)->concat($verifikasi)
            ->sortByDesc(fn (array $p) => $p['lewat'] ? 1 : 0)
            ->values();
    }
}
