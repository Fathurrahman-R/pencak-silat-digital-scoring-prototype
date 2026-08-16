<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchOfficial;
use App\Models\SilatMatch;
use App\Models\Tournament;
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
    public function show(Tournament $tournament, SilatMatch $match): View
    {
        $this->pastikanMilik($tournament, $match);

        return view('admin.partai.aparat', [
            'tournament' => $tournament,
            'match' => $match->load(['red.athletes', 'blue.athletes', 'bracket.weightClass', 'officials.user']),
            'jumlahJuri' => $tournament->peraturan()->jumlah_juri_tanding,
            'wasitTersedia' => User::role(MatchOfficial::ROLE_WASIT)->orderBy('name')->pluck('name', 'id'),
            'juriTersedia' => User::role(MatchOfficial::ROLE_JURI)->orderBy('name')->pluck('name', 'id'),
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
