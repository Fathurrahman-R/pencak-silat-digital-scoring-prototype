@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Resource"
                 description="Setiap resource menghasilkan resource key berbentuk {resource}.{aksi} yang dipakai di route, tampilan, dan menu."
                 :breadcrumb="['Resource' => null]">
    <x-slot:actions>
        <x-can :resource="rk('resources', ResourceAction::Create)">
            <x-ui.button :href="route('admin.resources.create')" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah resource
            </x-ui.button>
        </x-can>
    </x-slot:actions>

    <x-ui.table :table="$table"
                :selectable="$resources->reject(fn ($resource) => $resource->is_locked)->pluck('id')->all()"
                :headers="['key' => 'Key', 'label' => 'Label', 'group' => 'Grup', 0 => 'Aksi', 1 => '']">
        <x-slot:toolbar>
            <x-ui.table.toolbar :table="$table" placeholder="Cari resource…">
                <x-slot:filters>
                    <select name="group" class="form-select">
                        <option value="">Semua grup</option>
                        @foreach ($groups as $value => $label)
                            <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-slot:filters>

                <x-slot:bulk>
                    <x-can :resource="rk('resources', ResourceAction::Delete)">
                        <form method="POST" action="{{ route('admin.resources.bulk-destroy') }}">
                            @csrf
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>

                            <x-ui.button type="submit" variant="secondary" size="sm" class="border-danger text-danger">
                                <x-ui.icon name="trash-2" class="size-4" />
                                Hapus terpilih
                            </x-ui.button>
                        </form>
                    </x-can>
                </x-slot:bulk>
            </x-ui.table.toolbar>
        </x-slot:toolbar>

        @forelse ($resources as $resource)
            <x-ui.table.row :id="$resource->is_locked ? null : $resource->id">
                <x-ui.table.cell header>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.resources.show', $resource) }}" class="hover:underline">
                            <code>{{ $resource->key }}</code>
                        </a>

                        @if ($resource->is_locked)
                            <x-ui.badge variant="warning" pill>inti</x-ui.badge>
                        @endif
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $resource->label }}</x-ui.table.cell>
                <x-ui.table.cell>{{ $resource->group ?: '—' }}</x-ui.table.cell>

                <x-ui.table.cell>
                    @php($unmapped = $resource->mappings->whereNull('permission_id')->count())

                    <div class="flex flex-wrap items-center gap-1">
                        <x-ui.badge variant="primary">{{ $resource->mappings_count }} aksi</x-ui.badge>

                        @if ($unmapped > 0)
                            <x-ui.badge variant="danger" dot>{{ $unmapped }} belum dipetakan</x-ui.badge>
                        @endif
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1">
                        <x-ui.button :href="route('admin.resources.show', $resource)" variant="secondary" size="xs" title="Detail">
                            <x-ui.icon name="eye" class="h-4 w-4" />
                        </x-ui.button>

                        <x-can :resource="rk('resources', ResourceAction::Update)">
                            <x-ui.button :href="route('admin.resources.edit', $resource)" variant="secondary" size="xs" title="Ubah">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        </x-can>

                        @unless ($resource->is_locked)
                            <x-can :resource="rk('resources', ResourceAction::Delete)">
                                <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                             x-on:click="$dispatch('modal-open', 'hapus-resource-{{ $resource->id }}')">
                                    <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                                </x-ui.button>

                                <x-ui.modal :id="'hapus-resource-'.$resource->id" title="Hapus resource">
                                    <p>Yakin menghapus <code>{{ $resource->key }}</code> beserta {{ $resource->mappings_count }} pemetaannya?</p>

                                    <x-ui.alert variant="warning">
                                        Permission-nya tidak ikut dihapus — bisa jadi masih dipakai key lain. Cek daftar
                                        permission setelah ini kalau ingin membersihkannya.
                                    </x-ui.alert>

                                    <x-slot:footer>
                                        <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('modal-close', 'hapus-resource-{{ $resource->id }}')">Batal</x-ui.button>

                                        <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button variant="danger" type="submit">Hapus</x-ui.button>
                                        </form>
                                    </x-slot:footer>
                                </x-ui.modal>
                            </x-can>
                        @endunless
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty-state title="Belum ada resource"
                                      description="Buat resource pertama untuk mulai memakai resource key." />
                </td>
            </tr>
        @endforelse
        <x-slot:footer>{{ $resources->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
