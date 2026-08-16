<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel petugas timbang badan — Pasal 2 ayat 4.
 *
 * Hanya melayani kategori Tanding pada golongan yang memang menjalani timbang
 * badan. Pra Usia Dini dan Usia Dini 1 dikecualikan naskah, dan kategori Jurus
 * tidak mengenal kelas berat sama sekali.
 */
class WeightInController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        $cari = $request->string('q')->toString();

        $registrations = Registration::query()
            ->tanding()
            ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->when($cari !== '', fn ($query) => $query->whereHas(
                'athletes',
                fn ($athlete) => $athlete->where('name', 'like', "%{$cari}%"),
            ))
            ->with(['athletes', 'contingent', 'weightClass', 'weightIns'])
            ->get()
            // Golongan yang tidak menjalani timbang badan disaring di sini,
            // bukan di kueri, karena aturannya melekat pada enum golongan usia.
            ->filter(fn (Registration $r): bool => $r->weightClass->golongan_usia->adaTimbangBadan())
            ->sortBy(fn (Registration $r): string => $r->athletes->first()?->name ?? '')
            ->values();

        return view('admin.timbang.index', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'cari' => $cari,
        ]);
    }

    public function store(Request $request, Tournament $tournament, Registration $registration): RedirectResponse
    {
        abort_unless($registration->contingent->tournament_id === $tournament->id, 404);
        abort_unless($registration->kategori()->value === 'tanding', 404);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:10', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['weight' => 'Berat badan', 'notes' => 'Keterangan']);

        $kelas = $registration->weightClass;
        $athlete = $registration->athletes->first();

        // Hasil lolos ditetapkan sekarang, terhadap kelas yang berlaku saat
        // ini — bukan dihitung ulang saat dibaca. Kelas boleh disunting panitia
        // sesudahnya, dan hasil yang sudah ditandatangani tidak ikut berubah.
        $lolos = $kelas->memuatBerat((float) $data['weight']);

        DB::transaction(function () use ($registration, $athlete, $data, $lolos) {
            $registration->weightIns()->create([
                'athlete_id' => $athlete->id,
                'weight' => $data['weight'],
                'passed' => $lolos,
                'weighed_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            /*
             * Tidak lolos berarti gugur, dan lawannya menang tanpa bertanding.
             * Lolos setelah sebelumnya gugur mengembalikan status ke keadaan
             * sebelum penimbangan — penimbangan ulang memang dimaksudkan untuk
             * memberi kesempatan kedua, dan status yang tidak ikut pulih
             * membuat kesempatan itu tidak berarti apa-apa.
             */
            $registration->update([
                'status' => $lolos
                    ? ($registration->verified_at ? StatusPendaftaran::Terverifikasi : StatusPendaftaran::Diajukan)
                    : StatusPendaftaran::Gugur,
            ]);
        });

        $pesan = $lolos
            ? "{$athlete->name} lolos timbang badan di {$kelas->name}."
            : "{$athlete->name} tidak lolos {$kelas->name} ({$kelas->rentang()}) dan dinyatakan gugur.";

        return back()->with($lolos ? 'success' : 'warning', $pesan);
    }
}
