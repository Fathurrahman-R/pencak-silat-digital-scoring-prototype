<?php

namespace App\Http\Controllers;

use App\Models\Arena;
use App\Models\Bracket;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Bagan\PohonBagan;
use App\Support\Live\StatePartaiPublik;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Overlay siaran vMix -- lima halaman tanpa elemen interaktif, dipasang
 * sebagai Web Browser Input. Tidak ada middleware 'auth' di rute manapun
 * yang mengarah ke sini; satu-satunya pengamannya AllowLocalNetworkOnly.
 *
 * Payload state() sengaja lebih tipis dari PartaiScoringController::state():
 * tidak ada `officials` maupun `riwayat` beralasan pembatalan -- itu urusan
 * panel admin, bukan tontonan.
 */
class OverlayController extends Controller
{
    public function __construct(
        private readonly StatePartaiPublik $state,
        private readonly PohonBagan $pohon,
    ) {}

    /**
     * Di-cache satu detik, sama seperti live score publik.
     *
     * Satu gelanggang ditonton oleh lima Web Browser Input vMix sekaligus
     * (scorebug, dua kartu atlet, breakdown, hasil), dan semuanya menarik
     * ulang pada siaran yang sama. Tanpa cache, satu nilai terbit berarti
     * lima kali perhitungan state yang identik, tepat pada saat server sedang
     * melayani tekanan tombol juri berikutnya.
     *
     * Yang paling terlihat di siaran tidak ikut tertunda satu detik: angka
     * skor dan indikator juri dipasang dari muatan siarannya sendiri (lihat
     * resources/js/overlay/connection.js), bukan dari tarikan ini.
     */
    public function state(Arena $arena): JsonResponse
    {
        $data = Cache::remember("overlay-state-arena-{$arena->id}", 1, fn () => ($this->state)($arena));

        return response()->json($data);
    }

    public function scorebug(Arena $arena): View
    {
        return view('overlay.scorebug', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    public function athlete(Arena $arena, string $corner): View
    {
        abort_unless(in_array($corner, ['red', 'blue'], true), 404);

        return view('overlay.athlete', [
            'arena' => $arena,
            'corner' => $corner,
            'config' => $this->config($arena),
        ]);
    }

    public function breakdown(Arena $arena): View
    {
        return view('overlay.breakdown', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    public function result(Arena $arena): View
    {
        return view('overlay.result', ['arena' => $arena, 'config' => $this->config($arena)]);
    }

    /**
     * Bagan untuk tayangan antar partai. Kelasnya dipilih lewat ?kelas=ID
     * karena satu turnamen bisa punya ratusan kelas -- operator vMix
     * menyetel URL sekali saat mengarahkan sumber ke kelas yang mau
     * ditayangkan, bukan menebak dari partai yang sedang aktif.
     *
     * Rekap medali penuh menyusul Fase 8; belum ada mesin hitungnya di
     * codebase ini.
     */
    public function bracket(Request $request, Tournament $tournament): View
    {
        $weightClassId = $request->integer('kelas');
        $weightClass = $weightClassId
            ? WeightClass::where('tournament_id', $tournament->id)->find($weightClassId)
            : null;

        $bracket = $weightClass
            ? Bracket::where('weight_class_id', $weightClass->id)->with([
                'weightClass',
                'slots.registration.athletes', 'slots.registration.contingent',
                'matches.red.athletes', 'matches.red.contingent',
                'matches.blue.athletes', 'matches.blue.contingent',
            ])->first()
            : null;

        return view('overlay.bracket', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            // Pohon yang sama persis dengan halaman bagan panitia -- satu
            // penghitung, satu komponen, tiga permukaan.
            'pohon' => $bracket ? ($this->pohon)($bracket) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function config(Arena $arena): array
    {
        return [
            'arenaId' => $arena->id,
            'state' => route('overlay.state', $arena),
        ];
    }
}
