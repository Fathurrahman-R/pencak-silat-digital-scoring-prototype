@php
    use App\Enums\ResourceAction;
    use App\Enums\StatusInvoice;
@endphp

<x-layouts.admin heading="Tagihan"
                 :description="$contingent->name.' · '.$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent]),
                     'Tagihan' => null,
                 ]">
    @include('admin.kontingen.tabs')

    <div class="space-y-4">
        {{-- Kepala tagihan duduk di kartu: nomor dan nominal adalah isi
             halaman, bukan judulnya, jadi ia butuh permukaan seperti isi yang
             lain. --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                <div>
                    <p class="eyebrow">Nomor tagihan</p>
                    <p class="font-mono text-base2 text-ink">{{ $invoice->number }}</p>
                </div>

                <x-ui.badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-ui.badge>

                <div class="ms-auto text-right">
                    <p class="eyebrow">Total</p>
                    <p class="silat-angka font-mono text-[22px] font-semibold text-ink">{{ $invoice->rupiah() }}</p>
                </div>
            </div>
        </x-ui.card>

        @if ($invoice->status === StatusInvoice::Draf)
            <x-ui.alert variant="info" title="Tagihan masih mengikuti pendaftaran">
                Selama berstatus draf, isi tagihan disusun ulang setiap kali pendaftaran ditambah atau
                dihapus. Setelah dikunci, nominalnya tidak berubah lagi dan pendaftaran kontingen
                dibekukan sampai pembayaran selesai.
            </x-ui.alert>
        @elseif ($invoice->status === StatusInvoice::MenungguPembayaran)
            <x-ui.alert variant="warning" title="Pendaftaran dibekukan">
                Nominal terkunci sejak {{ $invoice->locked_at?->translatedFormat('d M Y, H:i') }}.
                Batalkan sesi pembayaran bila masih ingin menambah atau menghapus peserta.
            </x-ui.alert>
        @else
            <x-ui.alert variant="success" title="Tagihan lunas">
                Dibayar {{ $invoice->paid_at?->translatedFormat('d M Y, H:i') }}
                lewat {{ $invoice->paid_via === 'manual' ? 'pembayaran manual' : 'gerbang pembayaran' }}.
                Pendaftaran kontingen ini sudah bisa diverifikasi panitia.
            </x-ui.alert>
        @endif

        <x-ui.card title="Rincian">
            @forelse ($invoice->items as $item)
                <div class="flex items-start gap-4 border-b border-line py-3 last:border-0">
                    <p class="min-w-0 flex-1 text-base2 text-ink">{{ $item->description }}</p>
                    <p class="silat-angka shrink-0 font-mono text-base2 text-ink">{{ $item->rupiah() }}</p>
                </div>
            @empty
                <x-ui.empty-state title="Belum ada yang ditagih"
                                  description="Tagihan terisi begitu kontingen mendaftarkan peserta dan panitia menetapkan tarifnya." />
            @endforelse

            {{-- Tombolnya bagian dari tagihan, jadi ia tinggal di kaki kartu
                 yang sama — bukan mengambang sendiri di bawah halaman. --}}
            @resource(rk('invoice', ResourceAction::Update))
                @if ($invoice->status === StatusInvoice::Draf)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.turnamen.kontingen.tagihan.kunci', [$tournament, $contingent]) }}">
                            @csrf
                            <x-ui.button type="submit">Kunci tagihan dan lanjut bayar</x-ui.button>
                        </form>
                    </x-slot:footer>
                @elseif ($invoice->status === StatusInvoice::MenungguPembayaran)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.turnamen.kontingen.tagihan.batal', [$tournament, $contingent]) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">Batalkan sesi pembayaran</x-ui.button>
                        </form>
                    </x-slot:footer>
                @endif
            @endresource
        </x-ui.card>
    </div>
</x-layouts.admin>
