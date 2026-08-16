@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Kejuaraan"
                 description="Setiap kejuaraan memegang salinan peraturannya sendiri, beserta kelas tanding dan nomor jurus yang dipertandingkan."
                 :breadcrumb="['Kejuaraan' => null]">
    <x-slot:actions>
        @resource(rk('turnamen', ResourceAction::Export))
            <x-ui.button :href="route('admin.turnamen.export', request()->query())" variant="secondary" size="sm">
                <x-ui.icon name="download" class="h-4 w-4" />
                Ekspor CSV
            </x-ui.button>
        @endresource

        @resource(rk('turnamen', ResourceAction::Create))
            <x-ui.button :href="route('admin.turnamen.create')" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Kejuaraan baru
            </x-ui.button>
        @endresource
    </x-slot:actions>

    <x-ui.table :table="$table"
                :selectable="$tournaments->pluck('id')->all()"
                openable
                :headers="['name' => 'Nama', 0 => 'Penyelenggara', 'starts_on' => 'Jadwal', 1 => 'Gelanggang', 'status' => 'Status', 2 => '']">
        <x-slot:toolbar>
            <x-ui.table.toolbar :table="$table" placeholder="Cari nama, penyelenggara, tempat…">
                <x-slot:chips>
                    <x-ui.filter-chips param="status" all="Semua status" :options="$statuses" />
                </x-slot:chips>

                @resource(rk('turnamen', ResourceAction::Delete))
                    <x-slot:bulk>
                        <form method="POST" action="{{ route('admin.turnamen.bulk-destroy') }}">
                            @csrf
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>

                            <x-ui.button type="submit" variant="secondary" size="sm" class="border-danger text-danger">
                                <x-ui.icon name="trash-2" class="size-4" />
                                Hapus terpilih
                            </x-ui.button>
                        </form>
                    </x-slot:bulk>
                @endresource
            </x-ui.table.toolbar>
        </x-slot:toolbar>

        @forelse ($tournaments as $tournament)
            <x-ui.table.row :id="$tournament->id" :panel="route('admin.turnamen.panel', $tournament)">
                <x-ui.table.cell header>
                    {{ $tournament->name }}
                    @if ($tournament->venue)
                        <span class="block truncate text-xs font-normal text-ink-muted">{{ $tournament->venue }}</span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $tournament->organizer ?? '—' }}</x-ui.table.cell>

                <x-ui.table.cell>
                    @if ($tournament->starts_on)
                        {{ $tournament->starts_on->translatedFormat('d M Y') }}
                        @if ($tournament->ends_on && ! $tournament->ends_on->isSameDay($tournament->starts_on))
                            <span class="text-ink-muted">– {{ $tournament->ends_on->translatedFormat('d M Y') }}</span>
                        @endif
                    @else
                        —
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $tournament->arenas_count }}</x-ui.table.cell>

                <x-ui.table.cell>
                    <x-ui.badge :variant="$tournament->status->variant()">{{ $tournament->status->label() }}</x-ui.badge>
                </x-ui.table.cell>

                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1" data-row-action>
                        {{-- Membuka kejuaraan menjadikannya kejuaraan aktif,
                             dan seluruh bagiannya muncul di sidebar. --}}
                        @resource(rk('turnamen', ResourceAction::Update))
                            <x-ui.button :href="route('admin.turnamen.edit', $tournament)"
                                         variant="secondary" size="xs" title="Ubah">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @resource(rk('turnamen', ResourceAction::Delete))
                            <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                         x-on:click="$dispatch('modal-open', 'hapus-turnamen-{{ $tournament->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>

                            <x-ui.modal :id="'hapus-turnamen-'.$tournament->id" title="Hapus kejuaraan" size="sm">
                                Yakin menghapus <strong>{{ $tournament->name }}</strong>? Seluruh gelanggang,
                                kelas tanding, dan nomor jurus miliknya ikut terhapus.

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button"
                                                 x-on:click="$dispatch('modal-close', 'hapus-turnamen-{{ $tournament->id }}')">Batal</x-ui.button>

                                    <form method="POST" action="{{ route('admin.turnamen.destroy', $tournament) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" type="submit">Hapus</x-ui.button>
                                    </form>
                                </x-slot:footer>
                            </x-ui.modal>
                        @endresource
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty-state title="Belum ada kejuaraan"
                                      description="Kejuaraan baru langsung dibekali kelas tanding dan nomor jurus sesuai naskah 2025." />
                </td>
            </tr>
        @endforelse

        <x-slot:footer>{{ $tournaments->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.drawer-remote title="Detail kejuaraan" />
</x-layouts.admin>
