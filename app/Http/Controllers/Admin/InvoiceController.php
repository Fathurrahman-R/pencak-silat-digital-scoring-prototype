<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Keuangan\KelolaInvoice;
use App\Http\Controllers\Concerns\ScopesContingents;
use App\Http\Controllers\Controller;
use App\Models\Contingent;
use App\Models\Tournament;
use App\Support\Keuangan\InvoiceBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InvoiceController extends Controller
{
    use ScopesContingents;

    public function __construct(
        private readonly InvoiceBuilder $builder,
        private readonly KelolaInvoice $kelola,
    ) {}

    public function show(Tournament $tournament, Contingent $contingent): View
    {
        $this->pastikanBolehAkses($contingent);

        // Disusun ulang saat dibuka. Selama masih draf, tagihan memang
        // mengikuti pendaftaran — official yang baru menambah atlet harus
        // melihat angkanya berubah, bukan angka kemarin.
        $invoice = $this->builder->untuk($contingent);

        return view('admin.invoice.show', [
            'tournament' => $tournament,
            'contingent' => $contingent,
            'invoice' => $invoice->load('items'),
        ]);
    }

    /** Mengunci tagihan sebelum sesi pembayaran dibuat. */
    public function kunci(Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        try {
            $this->kelola->kunci($this->builder->untuk($contingent));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Tagihan dikunci. Pendaftaran kontingen dibekukan sampai pembayaran selesai.');
    }

    /** Membatalkan sesi pembayaran dan mencairkan kembali pendaftaran. */
    public function batal(Tournament $tournament, Contingent $contingent): RedirectResponse
    {
        $this->pastikanBolehAkses($contingent);

        try {
            $this->kelola->cairkan($contingent->invoice);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Sesi pembayaran dibatalkan. Pendaftaran bisa diubah lagi.');
    }
}
