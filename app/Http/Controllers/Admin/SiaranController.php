<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;

/**
 * Daftar alamat overlay siaran per gelanggang.
 *
 * Halaman overlay sendiri tidak punya navigasi apa pun: ia dipasang sebagai
 * Web Browser Input di vMix, tanpa tombol, tanpa menu, dan tidak bisa login.
 * Akibatnya alamatnya selama ini hanya hidup di dokumen operasional, dan
 * operator harus menyalin pola `/overlay/scorebug/{arena}` sambil menebak
 * angka gelanggangnya dari URL halaman lain. Menebak satu angka saja sudah
 * cukup untuk menayangkan gelanggang yang salah ke siaran.
 *
 * Halaman ini yang menyusun alamat itu, bukan operator. Ia hidup di dalam
 * panel admin -- yang dijaga izin hanyalah daftarnya; halaman overlaynya
 * tetap terbuka bagi siapa pun di LAN, sebagaimana harusnya, karena vMix
 * tidak bisa membawa sesi login.
 */
class SiaranController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $gelanggang = $tournament->arenas()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.siaran.index', [
            'tournament' => $tournament,
            'gelanggang' => $gelanggang->map(fn ($arena) => [
                'arena' => $arena,
                'halaman' => $this->halamanOverlay($arena),
            ]),
            'kelas' => $tournament->weightClasses()
                ->whereHas('bracket')
                ->orderBy('golongan_usia')
                ->orderBy('jenis_kelamin')
                ->orderBy('sort_order')
                ->get(),
            'bracketUrl' => route('overlay.bracket', $tournament),
        ]);
    }

    /**
     * Empat halaman per gelanggang, dipetakan ke Overlay Channel vMix yang
     * berbeda supaya bisa ditayangkan dan disembunyikan sendiri-sendiri.
     * Lower third punya dua alamat karena tiap sudut ditayangkan terpisah.
     *
     * @return array<int, array<string, string>>
     */
    private function halamanOverlay($arena): array
    {
        return [
            [
                'nama' => 'Scorebug',
                'channel' => 'Overlay 1',
                'isi' => 'Skor merah dan biru, timer, babak, nama atlet',
                'url' => route('overlay.scorebug', $arena),
            ],
            [
                'nama' => 'Lower third — sudut merah',
                'channel' => 'Overlay 2',
                'isi' => 'Nama, kontingen, dan foto pesilat sudut merah',
                'url' => route('overlay.athlete', [$arena, 'red']),
            ],
            [
                'nama' => 'Lower third — sudut biru',
                'channel' => 'Overlay 2',
                'isi' => 'Nama, kontingen, dan foto pesilat sudut biru',
                'url' => route('overlay.athlete', [$arena, 'blue']),
            ],
            [
                'nama' => 'Rincian nilai & hukuman',
                'channel' => 'Overlay 3',
                'isi' => 'Rincian per babak, kilatan singkat saat nilai baru terbit',
                'url' => route('overlay.breakdown', $arena),
            ],
            [
                'nama' => 'Papan hasil',
                'channel' => 'Overlay 4',
                'isi' => 'Hasil akhir partai beserta cara kemenangannya',
                'url' => route('overlay.result', $arena),
            ],
        ];
    }
}
