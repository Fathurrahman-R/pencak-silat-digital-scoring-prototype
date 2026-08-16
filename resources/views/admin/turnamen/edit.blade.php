@php
    use App\Enums\ResourceAction;
    use App\Enums\StatusTurnamen;
@endphp

<x-layouts.admin :heading="$tournament->name"
                 :breadcrumb="['Kejuaraan' => route('admin.turnamen.index'), $tournament->name => null]">
    <x-slot:actions>
        @resource(rk('gelanggang', ResourceAction::View))
            <x-ui.button :href="route('admin.turnamen.gelanggang.index', $tournament)" variant="secondary" size="sm">
                <x-ui.icon name="layout-grid" class="h-4 w-4" />
                Gelanggang ({{ $tournament->arenas_count }})
            </x-ui.button>
        @endresource
    </x-slot:actions>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Gelanggang" :value="$tournament->arenas_count" />
            <x-ui.stat label="Kelas tanding" :value="$tournament->weight_classes_count" />
            <x-ui.stat label="Nomor jurus" :value="$tournament->jurus_events_count" />
        </div>

        <x-ui.card title="Status kejuaraan">
            <div class="flex flex-wrap items-center gap-4">
                <x-ui.badge :variant="$tournament->status->variant()">{{ $tournament->status->label() }}</x-ui.badge>

                @if ($tournament->status->bolehUbahAturan())
                    <p class="text-base2 text-ink-secondary">
                        Setelan peraturan masih bisa diubah. Begitu kejuaraan dijalankan, setelannya
                        terkunci — partai yang sudah dinilai tidak boleh berubah dasar perhitungannya.
                    </p>
                @else
                    <p class="text-base2 text-ink-secondary">
                        Setelan peraturan terkunci dan tidak bisa dikembalikan ke draf.
                    </p>
                @endif

                @resource(rk('turnamen', ResourceAction::Update))
                    <div class="ms-auto flex gap-2">
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
                    </div>
                @endresource
            </div>
        </x-ui.card>

        <form method="POST" action="{{ route('admin.turnamen.update', $tournament) }}" class="space-y-6">
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
