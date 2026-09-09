<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JurusBracket;
use App\Models\JurusEvent;
use App\Models\Tournament;
use App\Support\Bagan\PohonBagan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response as HttpResponse;

/**
 * Bagan gugur nomor Jurus berformat battle: digambar dan dicetak.
 *
 * Cermin BracketController, dan cerminnya berhenti di controller: geometri
 * pohon, komponen tampilan, dan lembar cetaknya SATU, dipakai bersama bagan
 * Tanding lewat kontrak SumberBagan. Bagan yang dihitung ulang di tempat lain
 * menyimpang diam-diam, dan pohon yang garisnya meleset menyesatkan pembacanya
 * tentang siapa bertemu siapa -- kesalahan paling mahal di layar itu.
 *
 * Yang TIDAK ada di sini, dan sengaja: tukar tempat, kunci, dan buka kunci.
 * Ketiganya menyunting undian, dan penyuntingannya sudah punya jalannya
 * sendiri lewat SusunBaganJurus (susun ulang). Menambah tiga jalan tulis baru
 * ke tabel yang sama sebelum ada yang membutuhkannya hanya menambah tiga
 * tempat yang harus dijaga tetap konsisten.
 */
class JurusBaganController extends Controller
{
    public function __construct(private readonly PohonBagan $pohon) {}

    public function show(Tournament $tournament, JurusEvent $jurusEvent): View
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $bagan = $this->muat($jurusEvent);

        return view('admin.jurus.bagan', [
            'tournament' => $tournament,
            'nomor' => $jurusEvent,
            'bagan' => $bagan,
            'pohon' => ($this->pohon)($bagan),
        ]);
    }

    /**
     * Bagan Jurus siap cetak.
     *
     * Ukuran kertas dihitung dari pohonnya, bukan dipatok A4 -- alasan lengkap
     * ada di BracketController::cetak(). Angkanya dibaca dari sana juga, bukan
     * ditulis ulang: dua bagan yang dicetak dengan margin berbeda adalah dua
     * lembar yang tidak bisa ditempel berdampingan di papan pengumuman.
     */
    public function cetak(Tournament $tournament, JurusEvent $jurusEvent): HttpResponse
    {
        $this->pastikanMilik($tournament, $jurusEvent);

        $bagan = $this->muat($jurusEvent);
        $pohon = ($this->pohon)($bagan);

        // Piksel ke titik: 96 dpi layar, 72 titik per inci kertas.
        $keTitik = fn (float $px): float => round($px * 0.75, 1);

        $lebar = $keTitik($pohon['lebar'] + BracketController::MARGIN_CETAK * 2);
        $tinggi = $keTitik($pohon['tinggi'] + BracketController::KEPALA_CETAK + BracketController::MARGIN_CETAK * 2);

        $pdf = Pdf::loadView('admin.bagan.cetak-pdf', [
            'tournament' => $tournament,
            'judul' => $jurusEvent->nama(),
            'bracket' => $bagan,
            'pohon' => $pohon,
            'margin' => BracketController::MARGIN_CETAK,
            'kepala' => BracketController::KEPALA_CETAK,
        ])->setPaper([0, 0, $lebar, $tinggi]);

        /*
         * Jangan disimpan peramban: alamatnya tetap sama sepanjang kejuaraan
         * sementara isinya berubah tiap bagan disusun ulang, dan peramban yang
         * menyajikan salinan lama memberi panitia bagan usang tanpa satu pun
         * tanda.
         */
        return $pdf->stream('bagan-jurus-'.str($jurusEvent->nama())->slug().'.pdf')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    /** Bagan beserta seluruh yang digambar pohonnya, dalam satu rangkaian query. */
    private function muat(JurusEvent $jurusEvent): JurusBracket
    {
        $bagan = $jurusEvent->bagan()->with([
            'slots.registration.athletes',
            'slots.registration.contingent',
            'battles.red.athletes',
            'battles.red.contingent',
            'battles.blue.athletes',
            'battles.blue.contingent',
            'locker',
        ])->first();

        abort_unless($bagan, 404);

        return $bagan;
    }

    private function pastikanMilik(Tournament $tournament, JurusEvent $jurusEvent): void
    {
        abort_unless($jurusEvent->tournament_id === $tournament->id, 404);
    }
}
