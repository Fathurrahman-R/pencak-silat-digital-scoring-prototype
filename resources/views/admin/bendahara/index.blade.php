@php
    use App\Enums\ResourceAction;
    use App\Enums\StatusInvoice;

    $rupiah = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
@endphp

<x-layouts.admin heading="Bendahara"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bendahara' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('invoice', ResourceAction::Export))
            <x-si.tombol :tautan="route('admin.turnamen.bendahara.export', $tournament)" varian="kedua" ukuran="kecil" ikon="download">
                Ekspor rekap
            </x-si.tombol>
        @endresource
    </x-slot:actions>

    <div class="space-y-4">
        {{--
            Ringkasan dihitung dari seluruh tagihan, bukan dari yang sedang
            tersaring. Bendahara yang menyaring "menunggu pembayaran" tetap
            perlu melihat total masuk yang sebenarnya, bukan nol.
        --}}
        <div class="grid gap-4 sm:grid-cols-4">
            <x-si.angka label="Total masuk" :nilai="$rupiah($ringkasan['masuk'])" />
            <x-si.angka label="Tunggakan" :nilai="$rupiah($ringkasan['tunggakan'])" />
            <x-si.angka label="Kontingen lunas" :nilai="$ringkasan['lunas']" />
            <x-si.angka label="Belum lunas" :nilai="$ringkasan['belum']" />
        </div>

        {{-- Penyaring tinggal di kepala kartu yang disaringnya: satu benda,
             bukan dua potong yang kebetulan bertumpuk. --}}
        <x-si.kartu judul="Tagihan kontingen">
            <x-slot:aksi>
                <x-si.saring param="status" semua="Semua tagihan"
                             :pilihan="$statuses" :sekarang="$status ?: null" />
            </x-slot:aksi>

            @forelse ($invoices as $invoice)
                <div class="flex flex-wrap items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">{{ $invoice->contingent->name }}</p>
                        <p class="font-mono text-xs text-ink-muted">{{ $invoice->number }}</p>
                    </div>

                    <x-si.badge :varian="$invoice->status->varian()">{{ $invoice->status->label() }}</x-si.badge>

                    <p class="silat-angka min-w-[130px] text-right font-mono text-base2 text-ink">
                        {{ $invoice->rupiah() }}
                    </p>

                    <div class="flex gap-1">
                        @resource(rk('invoice', ResourceAction::View))
                            {{-- Kata, bukan ikon struk: tooltip tidak muncul di layar sentuh. --}}
                            <x-si.tombol :tautan="route('admin.turnamen.kontingen.tagihan.show', [$tournament, $invoice->contingent])"
                                         varian="kedua" ukuran="kecil">
                                Rincian tagihan
                            </x-si.tombol>
                        @endresource

                        @if (! $invoice->lunas())
                            @resource(rk('invoice', ResourceAction::Approve))
                                <x-si.tombol tipe="button" ukuran="kecil"
                                             x-on:click="$dispatch('modal-open', 'lunas-{{ $invoice->id }}')">
                                    Tandai lunas
                                </x-si.tombol>
                            @endresource
                        @elseif ($invoice->paid_via === 'manual')
                            @php($manual = $invoice->manualPayments()->first())

                            @if ($manual)
                                <x-si.tombol :tautan="route('admin.turnamen.bendahara.bukti', [$tournament, $invoice, $manual->id])"
                                             varian="kedua" ukuran="kecil" target="_blank">
                                    Lihat bukti bayar
                                </x-si.tombol>
                            @endif
                        @endif
                    </div>
                </div>

                @if (! $invoice->lunas())
                    @resource(rk('invoice', ResourceAction::Approve))
                        <x-si.modal :id="'lunas-'.$invoice->id" judul="Tandai lunas manual" ukuran="sedang">
                            <form method="POST" id="lunas-form-{{ $invoice->id }}"
                                  action="{{ route('admin.turnamen.bendahara.lunas', [$tournament, $invoice]) }}"
                                  enctype="multipart/form-data" class="space-y-4">
                                @csrf

                                <div class="rounded-lg bg-surface-inset p-3 text-base2">
                                    <p class="text-ink">{{ $invoice->contingent->name }}</p>
                                    <p class="silat-angka font-mono text-ink-muted">
                                        {{ $invoice->number }} · {{ $invoice->rupiah() }}
                                    </p>
                                </div>

                                <p class="text-xs text-ink-muted">
                                    Pembayaran manual tidak punya jejak di gerbang pembayaran. Yang bisa
                                    dipertanggungjawabkan hanya bukti yang Anda unggah dan nama Anda di
                                    jejak audit — karena itu keduanya wajib.
                                </p>

                                <x-si.isian name="note" label="Keterangan" wajib
                                            :id="'note-'.$invoice->id"
                                            bantuan="Nomor referensi transfer, nama penyetor, atau sebab lain yang bisa ditelusuri." />

                                <x-si.isian tipe="datetime-local" name="paid_at" label="Tanggal pembayaran" wajib
                                            :id="'paid-at-'.$invoice->id"
                                            :value="now()->format('Y-m-d\TH:i')" />

                                <x-si.unggah name="proof" label="Bukti pembayaran" wajib
                                             :id="'proof-'.$invoice->id"
                                             accept=".jpg,.jpeg,.png,.pdf"
                                             bantuan="JPG, PNG, atau PDF. Paling besar 4 MB." />
                            </form>

                            <x-slot:footer>
                                <x-si.tombol varian="kedua" tipe="button"
                                             x-on:click="$dispatch('modal-close', 'lunas-{{ $invoice->id }}')">Batal</x-si.tombol>
                                <x-si.tombol tipe="submit" form="lunas-form-{{ $invoice->id }}">Tandai lunas</x-si.tombol>
                            </x-slot:footer>
                        </x-si.modal>
                    @endresource
                @endif
            @empty
                <x-si.kosong judul="Belum ada tagihan"
                             syarat="Tagihan terbit begitu kontingen membuka halaman tagihannya." />
            @endforelse
        </x-si.kartu>
    </div>
</x-layouts.admin>
