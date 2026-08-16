@php
    use App\Enums\ResourceAction;
    use App\Enums\StatusTurnamen;
@endphp

<x-layouts.admin :heading="$tournament->name"
                 :breadcrumb="['Kejuaraan' => route('admin.turnamen.index'), $tournament->name => null]">
    {{--
        Deretan tombol pindah halaman sudah tidak ada di sini. Seluruh bagian
        kejuaraan berdiri sebagai submenu sidebar, jadi berpindah dari timbang
        badan ke verifikasi tidak perlu melewati halaman ini lebih dulu.
    --}}
    <div class="space-y-4">
        {{-- Status dan angka ringkas berdiri di satu baris: keduanya cuma
             sebaris nilai, dan memberi masing-masing satu kartu penuh
             menghabiskan tinggi layar sebelum formulirnya terlihat. --}}
        <x-ui.card title="Status kejuaraan">
            <x-slot:actions>
                @resource(rk('turnamen', ResourceAction::Update))
                    @foreach ($tournament->status->transisiSah() as $tujuan)
                        <form method="POST" action="{{ route('admin.turnamen.status', $tournament) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $tujuan->value }}">

                            <x-ui.button type="submit"
                                         :variant="$tujuan === StatusTurnamen::Berjalan ? 'primary' : 'secondary'"
                                         size="sm">
                                Tandai {{ $tujuan->label() }}
                            </x-ui.button>
                        </form>
                    @endforeach
                @endresource
            </x-slot:actions>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                <x-ui.badge :variant="$tournament->status->variant()">{{ $tournament->status->label() }}</x-ui.badge>

                <p class="max-w-[70ch] flex-1 text-base2 text-ink-secondary">
                    @if ($tournament->status->bolehUbahAturan())
                        Setelan peraturan masih bisa diubah. Begitu kejuaraan dijalankan, setelannya
                        terkunci — partai yang sudah dinilai tidak boleh berubah dasar perhitungannya.
                    @else
                        Setelan peraturan terkunci dan tidak bisa dikembalikan ke draf.
                    @endif
                </p>

                <div class="flex shrink-0 gap-5 border-line ps-5 sm:border-s">
                    @foreach ([
                        'Gelanggang' => $tournament->arenas_count,
                        'Kelas tanding' => $tournament->weight_classes_count,
                        'Nomor jurus' => $tournament->jurus_events_count,
                    ] as $label => $jumlah)
                        <div>
                            <div class="eyebrow">{{ $label }}</div>
                            <div class="num text-[15px] font-semibold text-ink">{{ $jumlah }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        <form method="POST" action="{{ route('admin.turnamen.update', $tournament) }}" class="space-y-4">
            @csrf
            @method('PUT')

            @include('admin.turnamen.form', ['tournament' => $tournament])

            <div class="flex items-center gap-2">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button :href="route('admin.turnamen.index')" variant="secondary">Kembali</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>
