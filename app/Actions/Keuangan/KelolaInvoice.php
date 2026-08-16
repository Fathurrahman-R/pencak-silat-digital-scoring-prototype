<?php

namespace App\Actions\Keuangan;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Support\Keuangan\InvoiceBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perpindahan status tagihan.
 *
 *   draf ──(official menekan bayar)──▶ menunggu_pembayaran ──(lunas)──▶ lunas
 *     ▲                                          │
 *     └──────(kedaluwarsa / dibatalkan)──────────┘
 *
 * Dipisahkan dari penyusunan isinya karena keduanya punya syarat yang berbeda:
 * isi tagihan boleh disusun ulang berkali-kali, sedangkan perpindahan status
 * membekukan dan mencairkan pendaftaran seluruh kontingen.
 */
class KelolaInvoice
{
    public function __construct(private readonly InvoiceBuilder $builder) {}

    /**
     * Mengunci tagihan sebelum sesi pembayaran dibuat.
     *
     * Isinya disusun ulang sekali lagi tepat sebelum dikunci, supaya nominal
     * yang dibayar benar-benar mencerminkan pendaftaran pada detik itu — bukan
     * hasil penyusunan terakhir yang mungkin sudah tertinggal.
     */
    public function kunci(Invoice $invoice): Invoice
    {
        if ($invoice->status !== StatusInvoice::Draf) {
            throw new RuntimeException(
                "Tagihan {$invoice->number} tidak berstatus draf, jadi tidak bisa dikunci lagi.",
            );
        }

        $invoice = $this->builder->susunUlang($invoice);

        if ($invoice->total_amount <= 0) {
            throw new RuntimeException(
                "Tagihan {$invoice->number} bernilai nol; tidak ada yang perlu dibayar.",
            );
        }

        $invoice->update([
            'status' => StatusInvoice::MenungguPembayaran,
            'locked_at' => now(),
        ]);

        return $invoice->refresh();
    }

    /**
     * Mengembalikan tagihan ke draf saat sesi pembayaran kedaluwarsa atau
     * dibatalkan, lalu mencairkan kembali pendaftaran kontingen.
     *
     * Tagihan yang sudah lunas tidak pernah kembali ke draf — pembatalan
     * sesudah dibayar dicatat sebagai pembatalan, bukan sebagai pengembalian
     * ke keadaan semula.
     */
    public function cairkan(Invoice $invoice): Invoice
    {
        if ($invoice->status !== StatusInvoice::MenungguPembayaran) {
            throw new RuntimeException(
                "Tagihan {$invoice->number} tidak sedang menunggu pembayaran.",
            );
        }

        $invoice->update([
            'status' => StatusInvoice::Draf,
            'locked_at' => null,
        ]);

        return $this->builder->susunUlang($invoice->refresh());
    }

    /**
     * Menandai tagihan lunas.
     *
     * Aman dipanggil ulang: gerbang pembayaran mengirim notifikasi yang sama
     * berkali-kali sampai menerima balasan sukses, dan tagihan yang sudah lunas
     * tidak boleh berubah nominal maupun waktu bayarnya karena itu.
     */
    public function tandaiLunas(Invoice $invoice, string $via): Invoice
    {
        if ($invoice->lunas()) {
            return $invoice;
        }

        if ($invoice->status === StatusInvoice::Draf) {
            throw new RuntimeException(
                "Tagihan {$invoice->number} belum dikunci, jadi nominalnya belum tentu yang dibayar.",
            );
        }

        DB::transaction(function () use ($invoice, $via) {
            $invoice->update([
                'status' => StatusInvoice::Lunas,
                'paid_at' => now(),
                'paid_via' => $via,
            ]);
        });

        return $invoice->refresh();
    }
}
