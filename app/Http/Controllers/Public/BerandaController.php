<?php

namespace App\Http\Controllers\Public;

use App\Enums\StatusTurnamen;
use App\Enums\Sudut;
use App\Http\Controllers\Controller;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Rekap\RekapMedali;
use App\Support\Scoring\TandingScoreCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Halaman depan publik.
 *
 * Sebelumnya alamat ini merender halaman jualan bawaan boilerplate: judul
 * "Hak akses yang berubah lewat panel, bukan lewat deploy", penjelasan RBAC
 * resource key, tabel harga tiga tingkat (Rp 0 / Rp 490rb / Hubungi kami), dan
 * footer "boilerplate Laravel". Tidak ada satu kata pun tentang pencak silat.
 *
 * Padahal inilah alamat pertama yang dibuka penonton dan official kontingen
 * lewat tunnel. Yang mereka cari cuma dua: kejuaraan mana yang sedang berjalan,
 * dan di mana melihat skornya.
 */
class BerandaController extends Controller
{
    public function __construct(
        private readonly RekapMedali $medali,
        private readonly TandingScoreCalculator $skor,
    ) {}

    public function __invoke(): View
    {
        $kejuaraan = Tournament::query()
            ->whereIn('status', [StatusTurnamen::Berjalan, StatusTurnamen::Draf])
            ->withCount('arenas')
            ->with('arenas:id,tournament_id,name')
            /*
             * Yang sedang berjalan selalu di atas, apa pun tanggalnya -- itu
             * yang dicari orang saat membuka halaman ini di tengah acara.
             */
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StatusTurnamen::Berjalan->value])
            ->orderBy('starts_on')
            ->take(6)
            ->get();

        /*
         * Kejuaraan yang sedang berjalan mendapat isi penuh; yang belum mulai
         * cukup tampil sebagai baris tanggal. Tidak ada gunanya menjalankan
         * query jadwal dan medali untuk kejuaraan yang belum punya satu partai
         * pun.
         */
        $berjalan = $kejuaraan->firstWhere('status', StatusTurnamen::Berjalan);

        return view('welcome', [
            'kejuaraan' => $kejuaraan,
            'berjalan' => $berjalan,
            'antrean' => $berjalan ? $this->antreanPartai($berjalan) : collect(),
            'papanGelanggang' => $berjalan ? $this->papanGelanggang($berjalan) : collect(),
            'medali' => $berjalan ? $this->medali->peringkatUmum($berjalan)->take(3) : collect(),
        ]);
    }

    /**
     * Antrean partai berikutnya -- yang paling dicari orang yang berdiri di GOR.
     *
     * Yang sudah selesai tidak ikut, dan urutannya urutan tayang gelanggang.
     * Pertanyaan yang dibawa penonton bukan "partai saya jam berapa" -- jadwal
     * tidak menyimpan jam -- melainkan "berapa partai lagi sebelum giliran
     * saya".
     */
    private function antreanPartai(Tournament $tournament): Collection
    {
        return SilatMatch::query()
            ->whereHas('bracket.weightClass', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('arena_id')
            ->where('status', '!=', SilatMatch::STATUS_SELESAI)
            ->with([
                'bracket.weightClass:id,name,jenis_kelamin,golongan_usia',
                'arena:id,name',
                'red.athletes:id,name',
                'blue.athletes:id,name',
            ])
            ->orderBy('arena_id')
            ->orderBy('order_in_arena')
            ->take(12)
            ->get();
    }

    /**
     * Skor partai yang sedang berjalan di tiap gelanggang.
     *
     * Halaman ini dibuka orang di tengah acara, dan pertanyaan pertamanya
     * selalu "berapa sekarang" -- bukan "gelanggang apa saja yang ada".
     */
    private function papanGelanggang(Tournament $tournament): Collection
    {
        return $tournament->arenas()
            ->with(['matches' => fn ($q) => $q
                ->where('status', SilatMatch::STATUS_BERLANGSUNG)
                ->with(['bracket.weightClass:id,name', 'red.athletes:id,name', 'blue.athletes:id,name'])
                ->limit(1),
            ])
            ->get()
            ->map(function ($arena) {
                $partai = $arena->matches->first();

                return [
                    'arena' => $arena,
                    'partai' => $partai,
                    'skor' => $partai ? [
                        'merah' => $this->skor->skor($partai, Sudut::Merah),
                        'biru' => $this->skor->skor($partai, Sudut::Biru),
                    ] : null,
                ];
            });
    }
}
