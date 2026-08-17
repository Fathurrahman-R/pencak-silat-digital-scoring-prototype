<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Support\Rekap\RekapMedali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rekap medali dan ekspor -- FR-J. */
class RekapController extends Controller
{
    public function __construct(private readonly RekapMedali $rekap) {}

    public function index(Tournament $tournament): View
    {
        return view('admin.rekap.index', [
            'tournament' => $tournament,
            'peringkatUmum' => $this->rekap->peringkatUmum($tournament),
            'tanding' => $this->rekap->tanding($tournament),
            'jurus' => $this->rekap->jurus($tournament),
        ]);
    }

    public function exportMedali(Tournament $tournament): StreamedResponse
    {
        $peringkat = $this->rekap->peringkatUmum($tournament);
        $nama = 'rekap-medali-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($peringkat) {
            $keluar = fopen('php://output', 'wb');
            fputcsv($keluar, ['Peringkat', 'Kontingen', 'Emas', 'Perak', 'Perunggu']);

            foreach ($peringkat as $i => $baris) {
                fputcsv($keluar, [$i + 1, $baris['kontingen'], $baris['emas'], $baris['perak'], $baris['perunggu']]);
            }

            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv']);
    }

    public function exportPeserta(Tournament $tournament): StreamedResponse
    {
        $registrasi = $tournament->contingents()
            ->with(['registrations' => fn ($q) => $q->with(['athletes', 'weightClass', 'jurusEvent'])])
            ->get()
            ->flatMap(fn ($k) => $k->registrations);

        $nama = 'peserta-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($registrasi) {
            $keluar = fopen('php://output', 'wb');
            fputcsv($keluar, ['Kontingen', 'Atlet', 'Nomor', 'Status']);

            foreach ($registrasi as $r) {
                fputcsv($keluar, [
                    $r->contingent->name,
                    $r->athletes->pluck('name')->implode(', '),
                    $r->namaNomor(),
                    $r->status->label(),
                ]);
            }

            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv']);
    }

    public function exportJadwal(Tournament $tournament): StreamedResponse
    {
        $matches = $tournament->arenas()
            ->with(['matches' => fn ($q) => $q->whereNotNull('order_in_arena')
                ->with(['red.athletes', 'red.contingent', 'blue.athletes', 'blue.contingent', 'bracket.weightClass'])
                ->orderBy('order_in_arena')])
            ->get()
            ->flatMap(fn ($arena) => $arena->matches->map(fn ($m) => [$arena->name, $m]));

        $nama = 'jadwal-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($matches) {
            $keluar = fopen('php://output', 'wb');
            fputcsv($keluar, ['Gelanggang', 'Urutan', 'Waktu', 'Kelas', 'Merah', 'Biru', 'Status']);

            foreach ($matches as [$namaArena, $partai]) {
                fputcsv($keluar, [
                    $namaArena,
                    $partai->order_in_arena,
                    optional($partai->scheduled_at)->format('Y-m-d H:i'),
                    $partai->bracket->weightClass->name,
                    $partai->red?->athletes->pluck('name')->implode(', '),
                    $partai->blue?->athletes->pluck('name')->implode(', '),
                    $partai->status,
                ]);
            }

            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv']);
    }
}
