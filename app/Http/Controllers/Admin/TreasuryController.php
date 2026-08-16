<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Keuangan\KelolaInvoice;
use App\Enums\StatusInvoice;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Tournament;
use App\Support\Keuangan\InvoiceBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel bendahara: rekapitulasi tagihan seluruh kontingen dan pencatatan
 * pembayaran di luar gerbang pembayaran.
 */
class TreasuryController extends Controller
{
    /** Bukti pembayaran memuat data rekening; tidak boleh ada di disk publik. */
    private const DISK = 'local';

    public function __construct(
        private readonly InvoiceBuilder $builder,
        private readonly KelolaInvoice $kelola,
    ) {}

    public function index(Request $request, Tournament $tournament): View
    {
        $status = $request->string('status')->toString();

        $semua = Invoice::query()
            ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->with(['contingent', 'items'])
            ->get();

        /*
         * Ringkasan dihitung dari seluruh tagihan, bukan dari yang tersaring.
         * Bendahara yang sedang menyaring "menunggu pembayaran" tetap perlu
         * melihat total masuk yang sebenarnya, bukan nol.
         */
        $ringkasan = [
            'masuk' => $semua->where('status', StatusInvoice::Lunas)->sum('total_amount'),
            'tunggakan' => $semua->where('status', '!=', StatusInvoice::Lunas)->sum('total_amount'),
            'lunas' => $semua->where('status', StatusInvoice::Lunas)->count(),
            'belum' => $semua->where('status', '!=', StatusInvoice::Lunas)->count(),
        ];

        return view('admin.bendahara.index', [
            'tournament' => $tournament,
            'invoices' => $status === ''
                ? $semua->sortBy(fn (Invoice $i): string => $i->contingent->name)->values()
                : $semua->where('status.value', $status)
                    ->sortBy(fn (Invoice $i): string => $i->contingent->name)->values(),
            'ringkasan' => $ringkasan,
            'status' => $status,
            'statuses' => StatusInvoice::options(),
        ]);
    }

    /**
     * Menandai tagihan lunas atas pembayaran yang tidak lewat gerbang.
     *
     * Bukti dan keterangan diwajibkan karena tidak ada pihak ketiga yang bisa
     * dimintai konfirmasi — yang tersisa hanya berkas yang diunggah bendahara
     * dan namanya di jejak audit.
     */
    public function tandaiLunas(Request $request, Tournament $tournament, Invoice $invoice): RedirectResponse
    {
        $this->pastikanMilik($tournament, $invoice);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:255'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'proof' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
        ], [
            'note.required' => 'Keterangan wajib diisi — nomor referensi transfer, nama penyetor, '
                .'atau sebab lain yang membuat pembayaran ini bisa ditelusuri kembali.',
            'proof.required' => 'Bukti pembayaran wajib diunggah.',
            'paid_at.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
        ], [
            'note' => 'Keterangan',
            'paid_at' => 'Tanggal pembayaran',
            'proof' => 'Bukti pembayaran',
        ]);

        // Tagihan yang masih draf dikunci lebih dulu, supaya nominal yang
        // dinyatakan lunas benar-benar nominal yang berlaku saat itu.
        if ($invoice->status === StatusInvoice::Draf) {
            try {
                $invoice = $this->kelola->kunci($this->builder->untuk($invoice->contingent));
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['note' => $e->getMessage()]);
            }
        }

        if ($invoice->lunas()) {
            throw ValidationException::withMessages([
                'note' => "Tagihan {$invoice->number} sudah berstatus lunas.",
            ]);
        }

        $berkas = $data['proof'];
        $path = $berkas->store("bukti-bayar/{$tournament->id}", self::DISK);

        DB::transaction(function () use ($invoice, $data, $path, $berkas) {
            $invoice->manualPayments()->create([
                'amount' => $invoice->total_amount,
                'note' => $data['note'],
                'proof_path' => $path,
                'proof_original_name' => $berkas->getClientOriginalName(),
                'paid_at' => $data['paid_at'],
                'recorded_by' => auth()->id(),
            ]);

            $this->kelola->tandaiLunas($invoice, 'manual');

            AuditLog::catat(
                action: 'invoice.lunas_manual',
                description: "Tagihan {$invoice->number} ditandai lunas manual sebesar {$invoice->rupiah()}.",
                auditable: $invoice,
                properties: [
                    'kontingen' => $invoice->contingent->name,
                    'nominal' => $invoice->total_amount,
                    'keterangan' => $data['note'],
                    'dibayar_pada' => $data['paid_at'],
                ],
            );
        });

        return back()->with('success', "Tagihan {$invoice->number} ditandai lunas.");
    }

    public function bukti(Tournament $tournament, Invoice $invoice, int $pembayaran): StreamedResponse
    {
        $this->pastikanMilik($tournament, $invoice);

        $manual = $invoice->manualPayments()->findOrFail($pembayaran);

        abort_unless($manual->proof_path && Storage::disk(self::DISK)->exists($manual->proof_path), 404);

        return Storage::disk(self::DISK)->response(
            $manual->proof_path,
            $manual->proof_original_name ?? 'bukti-bayar',
        );
    }

    public function export(Tournament $tournament): StreamedResponse
    {
        $invoices = Invoice::query()
            ->whereHas('contingent', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->with('contingent')
            ->get()
            ->sortBy(fn (Invoice $i): string => $i->contingent->name);

        $nama = 'rekap-keuangan-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($invoices) {
            $keluar = fopen('php://output', 'wb');

            fputcsv($keluar, ['Nomor', 'Kontingen', 'Status', 'Total', 'Dibayar', 'Cara bayar']);

            foreach ($invoices as $invoice) {
                fputcsv($keluar, [
                    $invoice->number,
                    $invoice->contingent->name,
                    $invoice->status->label(),
                    $invoice->total_amount,
                    $invoice->paid_at?->format('Y-m-d H:i'),
                    $invoice->paid_via,
                ]);
            }

            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv']);
    }

    private function pastikanMilik(Tournament $tournament, Invoice $invoice): void
    {
        abort_unless($invoice->contingent->tournament_id === $tournament->id, 404);
    }
}
