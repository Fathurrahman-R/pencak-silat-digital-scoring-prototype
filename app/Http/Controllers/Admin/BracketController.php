<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\WeightClass;
use App\Support\Bagan\BracketGenerator;
use App\Support\Bagan\PohonBagan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Panel bagan: menyusun, mengoreksi, dan mengunci bagan gugur tunggal per
 * kelas tanding.
 *
 * Bersarang di bawah kejuaraan seperti gelanggang dan tarif — tiap aksi
 * memastikan kelas tanding yang disebut memang milik kejuaraan di alamatnya.
 */
class BracketController extends Controller
{
    public function __construct(
        private readonly BracketGenerator $generator,
        private readonly PohonBagan $pohon,
    ) {}

    public function index(Request $request, Tournament $tournament): View
    {
        $semua = $tournament->weightClasses()->aktif()->get()
            ->map(function (WeightClass $k) {
                $k->setRelation('bracket', $k->bracket()->withCount('slots')->first());
                $k->peserta_sah = $this->generator->pesertaSah($k)->count();

                return $k;
            })
            ->sortBy('sort_order')
            ->values();

        /*
         * Naskah menurunkan 174 kelas tanding, dan satu kejuaraan biasa hanya
         * memakai belasan di antaranya. Menampilkan seluruhnya berarti daftar
         * sepanjang belasan layar yang 99% barisnya berbunyi "0 peserta sah",
         * dengan kelas yang benar-benar dipakai terkubur di tengahnya.
         *
         * Karena itu bawaannya menyaring ke kelas yang sudah punya peserta atau
         * sudah punya bagan. Kelas yang disembunyikan tetap dihitung dan
         * jumlahnya disebut di layar, supaya penyaringan ini tidak pernah
         * terasa seperti data yang hilang.
         */
        $tampil = $request->query('tampil', 'terpakai');
        $cari = trim((string) $request->query('q', ''));

        $kelas = $semua
            ->when($tampil === 'terpakai', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => $k->peserta_sah > 0 || $k->bracket !== null
            ))
            ->when($tampil === 'tersusun', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => $k->bracket !== null
            ))
            ->when($cari !== '', fn ($daftar) => $daftar->filter(
                fn (WeightClass $k) => str_contains(
                    mb_strtolower($k->name.' '.$k->jenis_kelamin->label().' '.$k->golongan_usia->label()),
                    mb_strtolower($cari)
                )
            ))
            ->values();

        return view('admin.bagan.index', [
            'tournament' => $tournament,
            'kelas' => $kelas,
            'jumlahSemua' => $semua->count(),
            'jumlahTerpakai' => $semua->filter(fn (WeightClass $k) => $k->peserta_sah > 0 || $k->bracket !== null)->count(),
            'tampil' => $tampil,
            'cari' => $cari,
        ]);
    }

    public function susun(Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        try {
            $this->generator->untukKelas($weightClass);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.turnamen.bagan.show', [$tournament, $weightClass])
            ->with('success', "Bagan {$weightClass->name} disusun.");
    }

    public function show(Tournament $tournament, WeightClass $weightClass): View
    {
        $this->pastikanMilik($tournament, $weightClass);

        $bracket = $weightClass->bracket()->with([
            'slots.registration.athletes',
            'slots.registration.contingent',
            'matches.red.athletes',
            'matches.red.contingent',
            'matches.blue.athletes',
            'matches.blue.contingent',
            'matches.winner',
        ])->first();

        abort_unless($bracket, 404);

        return view('admin.bagan.show', [
            'tournament' => $tournament,
            'weightClass' => $weightClass,
            'bracket' => $bracket,
            'pohon' => ($this->pohon)($bracket),
        ]);
    }

    public function tukar(Request $request, Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $data = $request->validate([
            'posisi_a' => ['required', 'integer'],
            'posisi_b' => ['required', 'integer', 'different:posisi_a'],
        ], [
            'posisi_b.different' => 'Pilih dua tempat yang berbeda untuk ditukar.',
        ]);

        $bracket = $weightClass->bracket()->firstOrFail();

        try {
            $this->generator->tukar($bracket, (int) $data['posisi_a'], (int) $data['posisi_b']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tempat ditukar.');
    }

    public function kunci(Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $bracket = $weightClass->bracket()->firstOrFail();

        try {
            $this->generator->kunci($bracket, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::catat(
            action: 'bagan.kunci',
            description: "Bagan {$weightClass->name} dikunci.",
            auditable: $bracket,
            properties: ['kelas_tanding' => $weightClass->name, 'ukuran' => $bracket->size],
        );

        return back()->with('success', "Bagan {$weightClass->name} dikunci.");
    }

    public function bukaKunci(Request $request, Tournament $tournament, WeightClass $weightClass): RedirectResponse
    {
        $this->pastikanMilik($tournament, $weightClass);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        $bracket = $weightClass->bracket()->firstOrFail();

        $this->generator->bukaKunci($bracket);

        AuditLog::catat(
            action: 'bagan.buka_kunci',
            description: "Bagan {$weightClass->name} dibuka kembali: {$data['alasan']}",
            auditable: $bracket,
            properties: ['kelas_tanding' => $weightClass->name, 'alasan' => $data['alasan']],
        );

        return back()->with('warning', 'Bagan dibuka kembali. Perubahan berikutnya tercatat di jejak audit.');
    }

    private function pastikanMilik(Tournament $tournament, WeightClass $weightClass): void
    {
        abort_unless($weightClass->tournament_id === $tournament->id, 404);
    }
}
