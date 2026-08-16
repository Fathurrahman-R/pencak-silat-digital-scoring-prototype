<?php

namespace App\Support\Keuangan;

use App\Enums\GolonganUsia;
use App\Enums\KategoriPertandingan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPendaftaran;
use App\Models\Contingent;
use App\Models\FeeSchedule;
use App\Models\Invoice;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun tagihan satu kontingen dari pendaftarannya.
 *
 * Satu baris tagihan per pendaftaran, bukan per orang. Nomor Ganda dan Regu
 * karena itu tertagih sekali untuk satu tim tanpa aturan tambahan — bentuk
 * datanya yang membuatnya benar, bukan klausa khusus di sini.
 *
 * Hanya dijalankan selama tagihan masih draf. Sesudah sesi pembayaran dibuat,
 * nominalnya terkunci: tanpa itu ada celah di mana official menekan bayar
 * untuk satu nominal, menambah atlet, lalu uang nominal lama yang masuk.
 */
class InvoiceBuilder
{
    public function untuk(Contingent $contingent): Invoice
    {
        $invoice = $contingent->invoice()->firstOrCreate([], [
            'number' => Invoice::nomorBerikutnya(),
            'status' => StatusInvoice::Draf,
            'total_amount' => 0,
        ]);

        if (! $invoice->status->ikutPendaftaran()) {
            return $invoice;
        }

        return $this->susunUlang($invoice);
    }

    public function susunUlang(Invoice $invoice): Invoice
    {
        $contingent = $invoice->contingent;
        $tarif = $contingent->tournament->feeSchedules()->get();

        $baris = collect();

        /*
         * Pendaftaran yang ditolak panitia tidak pernah sah, jadi tidak
         * ditagih. Yang gugur di timbang badan tetap ditagih — pesertanya
         * sudah didaftarkan dan tempatnya sudah disiapkan; pembatalan tidak
         * mengembalikan dana, hanya dicatat.
         */
        $pendaftaran = $contingent->registrations()
            ->where('status', '!=', StatusPendaftaran::Ditolak)
            ->with(['weightClass', 'jurusEvent', 'athletes'])
            ->get();

        foreach ($pendaftaran as $registration) {
            $nominal = $this->tarifUntuk($tarif, $registration);

            if ($nominal === null) {
                continue;
            }

            $baris->push([
                'registration_id' => $registration->id,
                'description' => $this->keterangan($registration),
                'amount' => $nominal,
            ]);
        }

        $tetap = $tarif->firstWhere('kind', FeeSchedule::KIND_KONTINGEN);

        if ($tetap !== null && $tetap->amount > 0) {
            $baris->push([
                'registration_id' => null,
                'description' => $tetap->label ?: 'Biaya tetap kontingen',
                'amount' => $tetap->amount,
            ]);
        }

        DB::transaction(function () use ($invoice, $baris) {
            $invoice->items()->delete();
            $invoice->items()->createMany($baris->all());
            $invoice->update(['total_amount' => $baris->sum('amount')]);
        });

        return $invoice->refresh();
    }

    /**
     * Tarif yang berlaku untuk satu pendaftaran.
     *
     * Baris yang menyebut golongan usia tertentu mengalahkan baris yang
     * mengosongkannya, dan yang menyebut kategori mengalahkan yang tidak.
     * Panitia karena itu cukup menulis satu tarif umum lalu mengecualikan yang
     * berbeda — bukan mengisi delapan golongan kali dua kategori satu per satu.
     *
     * @param  Collection<int, FeeSchedule>  $tarif
     */
    private function tarifUntuk(Collection $tarif, Registration $registration): ?int
    {
        $kategori = $registration->kategori();
        $golongan = $this->golonganPendaftaran($registration);

        $kandidat = $tarif
            ->where('kind', FeeSchedule::KIND_NOMOR)
            ->filter(fn (FeeSchedule $t): bool => ($t->kategori === null || $t->kategori === $kategori)
                && ($t->golongan_usia === null || $t->golongan_usia === $golongan))
            ->sortByDesc(fn (FeeSchedule $t): int => ($t->kategori !== null ? 2 : 0) + ($t->golongan_usia !== null ? 1 : 0));

        return $kandidat->first()?->amount;
    }

    private function golonganPendaftaran(Registration $registration): ?GolonganUsia
    {
        return $registration->kategori() === KategoriPertandingan::Tanding
            ? $registration->weightClass?->golongan_usia
            : $registration->jurusEvent?->golongan_usia;
    }

    /**
     * Keterangan baris disimpan sebagai teks jadi, bukan dirakit saat
     * ditampilkan. Kelas dan nomor boleh disunting panitia sesudahnya,
     * sementara tagihan yang sudah dibayar harus tetap terbaca persis seperti
     * saat dibayar.
     */
    private function keterangan(Registration $registration): string
    {
        $nama = $registration->namaNomor();
        $pesilat = $registration->athletes->pluck('name')->implode(', ');

        return $pesilat === '' ? $nama : "{$nama} — {$pesilat}";
    }
}
