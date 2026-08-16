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
            <x-ui.button :href="route('admin.turnamen.bendahara.export', $tournament)" variant="secondary" size="sm">
                <x-ui.icon name="download" class="h-4 w-4" />
                Ekspor rekap
            </x-ui.button>
        @endresource
    </x-slot:actions>

    <div class="space-y-6">
        @if ($errors->any())
            <x-ui.alert variant="danger" title="Pembayaran tidak tercatat">
                {{ $errors->first() }}
            </x-ui.alert>
        @endif

        {{--
            Ringkasan dihitung dari seluruh tagihan, bukan dari yang sedang
            tersaring. Bendahara yang menyaring "menunggu pembayaran" tetap
            perlu melihat total masuk yang sebenarnya, bukan nol.
        --}}
        <div class="grid gap-4 sm:grid-cols-4">
            <x-ui.stat label="Total masuk" :value="$rupiah($ringkasan['masuk'])" />
            <x-ui.stat label="Tunggakan" :value="$rupiah($ringkasan['tunggakan'])" />
            <x-ui.stat label="Kontingen lunas" :value="$ringkasan['lunas']" />
            <x-ui.stat label="Belum lunas" :value="$ringkasan['belum']" />
        </div>

        <div class="flex flex-wrap gap-2">
            <x-ui.button :href="route('admin.turnamen.bendahara.index', $tournament)"
                         :variant="$status === '' ? 'primary' : 'secondary'" size="sm">
                Semua
            </x-ui.button>

            @foreach ($statuses as $nilai => $label)
                <x-ui.button :href="route('admin.turnamen.bendahara.index', [$tournament, 'status' => $nilai])"
                             :variant="$status === $nilai ? 'primary' : 'secondary'" size="sm">
                    {{ $label }}
                </x-ui.button>
            @endforeach
        </div>

        <x-ui.card>
            @forelse ($invoices as $invoice)
                <div class="flex flex-wrap items-center gap-4 border-b border-line py-4 last:border-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">{{ $invoice->contingent->name }}</p>
                        <p class="font-mono text-xs text-ink-muted">{{ $invoice->number }}</p>
                    </div>

                    <x-ui.badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-ui.badge>

                    <p class="silat-angka min-w-[130px] text-right font-mono text-base2 text-ink">
                        {{ $invoice->rupiah() }}
                    </p>

                    <div class="flex gap-1">
                        @resource(rk('invoice', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.kontingen.tagihan.show', [$tournament, $invoice->contingent])"
                                         variant="secondary" size="xs" title="Lihat rincian">
                                <x-ui.icon name="receipt" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @if (! $invoice->lunas())
                            @resource(rk('invoice', ResourceAction::Approve))
                                <x-ui.button type="button" size="xs"
                                             x-on:click="$dispatch('modal-open', 'lunas-{{ $invoice->id }}')">
                                    Tandai lunas
                                </x-ui.button>
                            @endresource
                        @elseif ($invoice->paid_via === 'manual')
                            @php($manual = $invoice->manualPayments()->first())

                            @if ($manual)
                                <x-ui.button :href="route('admin.turnamen.bendahara.bukti', [$tournament, $invoice, $manual->id])"
                                             variant="secondary" size="xs" target="_blank" title="Lihat bukti">
                                    <x-ui.icon name="file-text" class="h-4 w-4" />
                                </x-ui.button>
                            @endif
                        @endif
                    </div>
                </div>

                @if (! $invoice->lunas())
                    @resource(rk('invoice', ResourceAction::Approve))
                        <x-ui.modal :id="'lunas-'.$invoice->id" title="Tandai lunas manual" size="md">
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

                                <x-ui.input name="note" label="Keterangan" required
                                            :id="'note-'.$invoice->id"
                                            hint="Nomor referensi transfer, nama penyetor, atau sebab lain yang bisa ditelusuri." />

                                <x-ui.input type="datetime-local" name="paid_at" label="Tanggal pembayaran" required
                                            :id="'paid-at-'.$invoice->id"
                                            :value="now()->format('Y-m-d\TH:i')" />

                                <x-ui.file-upload name="proof" label="Bukti pembayaran" required
                                                  :id="'proof-'.$invoice->id"
                                                  accept=".jpg,.jpeg,.png,.pdf"
                                                  hint="JPG, PNG, atau PDF. Paling besar 4 MB." />
                            </form>

                            <x-slot:footer>
                                <x-ui.button variant="secondary" type="button"
                                             x-on:click="$dispatch('modal-close', 'lunas-{{ $invoice->id }}')">Batal</x-ui.button>
                                <x-ui.button type="submit" form="lunas-form-{{ $invoice->id }}">Tandai lunas</x-ui.button>
                            </x-slot:footer>
                        </x-ui.modal>
                    @endresource
                @endif
            @empty
                <x-ui.empty-state title="Belum ada tagihan"
                                  description="Tagihan terbit begitu kontingen membuka halaman tagihannya." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
