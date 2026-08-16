@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Kontingen"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('kontingen', ResourceAction::Create))
            <x-ui.button :href="route('admin.turnamen.kontingen.create', $tournament)" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Daftarkan kontingen
            </x-ui.button>
        @endresource
    </x-slot:actions>

    <x-ui.table :table="$table"
                openable
                :headers="['name' => 'Kontingen', 'region' => 'Daerah', 0 => 'Official', 1 => 'Atlet', 2 => 'Pendaftaran', 3 => '']">
        <x-slot:toolbar>
            <x-ui.table.toolbar :table="$table" placeholder="Cari kontingen, daerah, kontak…" />
        </x-slot:toolbar>

        @forelse ($contingents as $contingent)
            <x-ui.table.row :id="$contingent->id" :panel="route('admin.turnamen.kontingen.panel', [$tournament, $contingent])">
                <x-ui.table.cell header>
                    {{ $contingent->name }}
                    @if ($contingent->contact_name)
                        <span class="block truncate text-xs font-normal text-ink-muted">
                            {{ $contingent->contact_name }}{{ $contingent->contact_phone ? ' · '.$contingent->contact_phone : '' }}
                        </span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $contingent->region ?? '—' }}</x-ui.table.cell>

                <x-ui.table.cell>
                    @if ($contingent->official)
                        {{ $contingent->official->name }}
                    @else
                        <span class="text-ink-muted">Belum ditentukan</span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $contingent->athletes_count }}</x-ui.table.cell>
                <x-ui.table.cell>{{ $contingent->registrations_count }}</x-ui.table.cell>

                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1" data-row-action>
                        @resource(rk('atlet', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.kontingen.atlet.index', [$tournament, $contingent])"
                                         variant="secondary" size="xs" title="Atlet">
                                <x-ui.icon name="users" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @resource(rk('pendaftaran', ResourceAction::View))
                            <x-ui.button :href="route('admin.turnamen.kontingen.pendaftaran.index', [$tournament, $contingent])"
                                         variant="secondary" size="xs" title="Pendaftaran nomor">
                                <x-ui.icon name="clipboard-list" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @resource(rk('kontingen', ResourceAction::Update))
                            <x-ui.button :href="route('admin.turnamen.kontingen.edit', [$tournament, $contingent])"
                                         variant="secondary" size="xs" title="Ubah">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @resource(rk('kontingen', ResourceAction::Delete))
                            <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                         x-on:click="$dispatch('modal-open', 'hapus-kontingen-{{ $contingent->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>

                            <x-ui.modal :id="'hapus-kontingen-'.$contingent->id" title="Hapus kontingen" size="sm">
                                Yakin menghapus <strong>{{ $contingent->name }}</strong>? Seluruh atlet dan
                                pendaftarannya ikut terhapus.

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button"
                                                 x-on:click="$dispatch('modal-close', 'hapus-kontingen-{{ $contingent->id }}')">Batal</x-ui.button>

                                    <form method="POST" action="{{ route('admin.turnamen.kontingen.destroy', [$tournament, $contingent]) }}">
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
                    <x-ui.empty-state title="Belum ada kontingen"
                                      description="Satu tagihan terbit per kontingen, jadi kontingen adalah satuan pendaftaran sekaligus satuan penagihan." />
                </td>
            </tr>
        @endforelse

        <x-slot:footer>{{ $contingents->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.drawer-remote title="Detail kontingen" />
</x-layouts.admin>
