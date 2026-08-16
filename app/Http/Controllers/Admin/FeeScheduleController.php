<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GolonganUsia;
use App\Enums\KategoriPertandingan;
use App\Http\Controllers\Controller;
use App\Models\FeeSchedule;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeScheduleController extends Controller
{
    public function index(Tournament $tournament): View
    {
        return view('admin.tarif.index', [
            'tournament' => $tournament,
            'tarifNomor' => $tournament->feeSchedules()->nomor()->get(),
            'tarifKontingen' => $tournament->feeSchedules()->kontingen()->first(),
            'kategori' => KategoriPertandingan::options(),
            'golongan' => GolonganUsia::options(),
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->pastikanMasihDraf($tournament);

        $data = $request->validate([
            'kategori' => ['nullable', Rule::enum(KategoriPertandingan::class)],
            'golongan_usia' => ['nullable', Rule::enum(GolonganUsia::class)],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], attributes: [
            'kategori' => 'Kategori',
            'golongan_usia' => 'Golongan usia',
            'amount' => 'Nominal',
        ]);

        // updateOrCreate, bukan create: satu kombinasi kategori dan golongan
        // hanya boleh punya satu tarif, dan memasukkan kombinasi yang sama
        // berarti mengoreksi angkanya, bukan menambah baris kembar.
        $tournament->feeSchedules()->updateOrCreate(
            [
                'kind' => FeeSchedule::KIND_NOMOR,
                'kategori' => $data['kategori'] ?: null,
                'golongan_usia' => $data['golongan_usia'] ?: null,
            ],
            ['amount' => $data['amount']],
        );

        return back()->with('success', 'Tarif tersimpan.');
    }

    public function storeKontingen(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->pastikanMasihDraf($tournament);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'label' => ['nullable', 'string', 'max:255'],
        ], attributes: ['amount' => 'Nominal', 'label' => 'Keterangan']);

        $tournament->feeSchedules()->updateOrCreate(
            ['kind' => FeeSchedule::KIND_KONTINGEN, 'kategori' => null, 'golongan_usia' => null],
            ['amount' => $data['amount'], 'label' => $data['label'] ?: 'Biaya tetap kontingen'],
        );

        return back()->with('success', 'Biaya tetap kontingen tersimpan.');
    }

    public function destroy(Tournament $tournament, FeeSchedule $feeSchedule): RedirectResponse
    {
        $this->pastikanMasihDraf($tournament);

        abort_unless($feeSchedule->tournament_id === $tournament->id, 404);

        $feeSchedule->delete();

        return back()->with('success', 'Tarif dihapus.');
    }

    /**
     * Tarif hanya bisa diubah selama kejuaraan masih draf.
     *
     * Sesudah pendaftaran dibuka, mengubah tarif berarti dua kontingen membayar
     * harga berbeda untuk nomor yang sama — dan yang membayar lebih dulu tidak
     * punya cara mengetahuinya.
     */
    private function pastikanMasihDraf(Tournament $tournament): void
    {
        abort_unless(
            $tournament->status->bolehUbahAturan(),
            403,
            'Tarif terkunci karena kejuaraan sudah '.strtolower($tournament->status->label()).'.',
        );
    }
}
