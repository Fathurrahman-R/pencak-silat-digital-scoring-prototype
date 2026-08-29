<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
use App\Support\Bagan\KetersediaanAparat;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Penugasan wasit dan juri per partai — Pasal 13 dan 16.
 *
 * Jumlah juri tidak bebas ditentukan di sini; ia mengikuti
 * `jumlah_juri_tanding` dari setelan peraturan kejuaraan, supaya panel juri
 * yang nanti dibuka tiap juri selalu sesuai formasi yang berlaku.
 */
class AparatController extends Controller
{
    public function __construct(private readonly KetersediaanAparat $ketersediaan) {}

    public function show(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        $wasit = User::role(MatchOfficial::ROLE_WASIT)->orderBy('name')->pluck('name', 'id');
        $juri = User::role(MatchOfficial::ROLE_JURI)->orderBy('name')->pluck('name', 'id');

        /*
         * Siapa yang sedang dipakai di gelanggang lain ikut dibawa, beserta
         * SEBABNYA. Daftar yang menawarkan semua orang membuat panitia
         * menugaskan bentrok tanpa tahu, dan yang ketahuan bukan sistemnya
         * melainkan kursi juri yang kosong saat partai dimulai.
         *
         * Yang bentrok tidak dihapus dari daftar: panitia yang mencari nama
         * dan tidak menemukannya akan mengira orangnya belum terdaftar sama
         * sekali, lalu membuat akun kedua.
         */
        $bentrok = $this->ketersediaan->bentrok(
            $match,
            $wasit->keys()->merge($juri->keys())->unique()->values(),
        );

        return view('admin.partai.aparat', [
            'tournament' => $tournament,
            'match' => $match->load(['red.athletes', 'blue.athletes', 'bracket.weightClass', 'officials.user', 'arena']),
            'jumlahJuri' => $tournament->peraturan()->jumlah_juri_tanding,
            'wasitTersedia' => $wasit,
            'juriTersedia' => $juri,
            'bentrok' => $bentrok,
        ]);
    }

    public function store(Request $request, Tournament $tournament, SilatMatch $match): RedirectResponse
    {
        $this->pastikanMilik($tournament, $match);

        $jumlahJuri = $tournament->peraturan()->jumlah_juri_tanding;

        $data = $request->validate([
            'wasit_id' => ['required', Rule::exists('users', 'id')],
            'juri_id' => ['required', 'array', 'size:'.$jumlahJuri],
            'juri_id.*' => ['required', 'distinct', Rule::exists('users', 'id')],
        ], [
            'juri_id.size' => "Jumlah juri harus tepat {$jumlahJuri} orang, mengikuti setelan peraturan kejuaraan.",
            'juri_id.*.distinct' => 'Satu orang tidak bisa ditugaskan sebagai lebih dari satu juri dalam partai yang sama.',
        ]);

        if (in_array((int) $data['wasit_id'], array_map('intval', $data['juri_id']), true)) {
            throw ValidationException::withMessages(['wasit_id' => 'Wasit tidak boleh merangkap juri dalam partai yang sama.']);
        }

        /*
         * Penjaga bentrok ditegakkan di sini, bukan hanya ditampilkan di
         * daftar. Satu orang tidak bisa berdiri di dua gelanggang sekaligus,
         * dan penugasan yang lolos akan berakhir jadi kursi kosong saat partai
         * dimulai -- kesalahan yang baru terlihat di depan penonton.
         */
        $dipilih = collect([$data['wasit_id']])->merge($data['juri_id'])->map(fn ($id) => (int) $id);
        $bentrok = $this->ketersediaan->bentrok($match, $dipilih);

        if ($bentrok !== []) {
            $nama = User::whereIn('id', array_keys($bentrok))->pluck('name', 'id');

            throw ValidationException::withMessages([
                'wasit_id' => collect($bentrok)
                    ->map(fn (string $sebab, int $id) => ($nama[$id] ?? 'Aparat').' sudah bertugas — '.$sebab.'.')
                    ->values()
                    ->all(),
            ]);
        }

        DB::transaction(function () use ($match, $data) {
            $match->officials()->delete();

            $match->officials()->create([
                'user_id' => $data['wasit_id'],
                'role' => MatchOfficial::ROLE_WASIT,
            ]);

            foreach (array_values($data['juri_id']) as $indeks => $userId) {
                $match->officials()->create([
                    'user_id' => $userId,
                    'role' => MatchOfficial::ROLE_JURI,
                    'number' => $indeks + 1,
                ]);
            }
        });

        return back()->with('success', 'Aparat partai ditetapkan.');
    }

    private function pastikanMilik(Tournament $tournament, SilatMatch $match): void
    {
        abort_unless($match->bracket->weightClass->tournament_id === $tournament->id, 404);
    }
}
