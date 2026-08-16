<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GolonganUsia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRuleSettingRequest;
use App\Models\Tournament;
use App\Models\TournamentRuleSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Setelan peraturan satu kejuaraan.
 *
 * Hanya bisa disunting selama kejuaraan berstatus Draf. Sesudah itu terkunci,
 * karena partai yang sudah dinilai tidak boleh berubah dasar perhitungannya —
 * termasuk yang hasilnya sudah disahkan dewan juri dan diumumkan.
 */
class TournamentRuleController extends Controller
{
    public function edit(Tournament $tournament): View
    {
        $setelan = $tournament->ruleSetting()->firstOrCreate([], TournamentRuleSetting::bawaan());

        return view('admin.turnamen.peraturan', [
            'tournament' => $tournament,
            'setelan' => $setelan,
            'golonganTanding' => array_values(array_filter(
                GolonganUsia::cases(),
                static fn (GolonganUsia $g): bool => $g->adaTanding(),
            )),
        ]);
    }

    public function update(UpdateRuleSettingRequest $request, Tournament $tournament): RedirectResponse
    {
        $this->pastikanMasihDraf($tournament);

        $tournament->peraturan()->update($request->payload());

        return redirect()
            ->route('admin.turnamen.peraturan.edit', $tournament)
            ->with('success', 'Setelan peraturan disimpan.');
    }

    /** Kembalikan seluruh setelan ke angka naskah 2025. */
    public function reset(Tournament $tournament): RedirectResponse
    {
        $this->pastikanMasihDraf($tournament);

        $tournament->peraturan()->update(TournamentRuleSetting::bawaan());

        return redirect()
            ->route('admin.turnamen.peraturan.edit', $tournament)
            ->with('success', 'Setelan dikembalikan ke angka naskah 2025.');
    }

    /**
     * Penguncian ditegakkan di server, bukan sekadar dengan menyembunyikan
     * formulirnya. Kalau hanya tampilan yang dikunci, satu permintaan yang
     * disusun tangan sudah cukup untuk mengubah dasar perhitungan pertandingan
     * yang sedang berjalan.
     */
    private function pastikanMasihDraf(Tournament $tournament): void
    {
        abort_unless(
            $tournament->status->bolehUbahAturan(),
            403,
            'Setelan peraturan terkunci karena kejuaraan sudah '.strtolower($tournament->status->label()).'.',
        );
    }
}
