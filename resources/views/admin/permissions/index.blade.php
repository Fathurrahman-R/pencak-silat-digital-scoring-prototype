@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Permission"
                 description="Izin mentah yang dibagikan ke role. Resource key menunjuk ke sini lewat pemetaan."
                 :breadcrumb="['Permission' => null]">
    <x-slot:actions>
        <x-can :resource="rk('permissions', ResourceAction::Create)">
            <x-ui.button :href="route('admin.permissions.create')" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah permission
            </x-ui.button>
        </x-can>
    </x-slot:actions>

    <x-ui.table :table="$table"
                :selectable="$permissions->reject(fn ($permission) => $permission->is_locked)->pluck('id')->all()"
                :headers="['name' => 'Nama', 'group' => 'Grup', 0 => 'Dipetakan dari key', 1 => 'Role', 2 => '']">
        <x-slot:toolbar>
            <x-ui.table.toolbar :table="$table" placeholder="Cari nama permission…">
                <x-slot:filters>
                    <select name="group" class="form-select">
                        <option value="">Semua grup</option>
                        @foreach ($groups as $value => $label)
                            <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-slot:filters>

                <x-slot:chips>
                    <x-ui.filter-chips param="status" all="Semua"
                                       :options="['dipakai' => 'Dipakai resource key', 'yatim' => 'Tidak dipakai']" />
                </x-slot:chips>

                <x-slot:bulk>
                    <x-can :resource="rk('permissions', ResourceAction::Delete)">
                        <form method="POST" action="{{ route('admin.permissions.bulk-destroy') }}">
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

        @forelse ($permissions as $permission)
            <x-ui.table.row :id="$permission->is_locked ? null : $permission->id">
                <x-ui.table.cell header>
                    <div class="flex items-center gap-2">
                        <code>{{ $permission->name }}</code>
                        @if ($permission->is_locked)
                            <x-ui.badge variant="warning" pill>inti</x-ui.badge>
                        @endif
                    </div>

                    @if ($permission->label)
                        <span class="block text-xs font-normal text-ink-muted">{{ $permission->label }}</span>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $permission->group ?: '—' }}</x-ui.table.cell>

                <x-ui.table.cell>
                    @if ($permission->mappings_count === 0)
                        <x-ui.badge variant="warning">tidak dipakai key</x-ui.badge>
                    @else
                        <div class="flex flex-wrap gap-1">
                            @foreach ($permission->mappings as $mapping)
                                <code class="rounded-sm bg-code px-1.5 py-0.5 font-mono text-xs text-code-ink">{{ $mapping->key() }}</code>
                            @endforeach
                        </div>
                    @endif
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $permission->roles_count }}</x-ui.table.cell>

                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1">
                        <x-can :resource="rk('permissions', ResourceAction::Update)">
                            <x-ui.button :href="route('admin.permissions.edit', $permission)" variant="secondary" size="xs" title="Ubah">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        </x-can>

                        @unless ($permission->is_locked)
                            <x-can :resource="rk('permissions', ResourceAction::Delete)">
                                <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                             x-on:click="$dispatch('modal-open', 'hapus-permission-{{ $permission->id }}')">
                                    <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                                </x-ui.button>

                                <x-ui.modal :id="'hapus-permission-'.$permission->id" title="Hapus permission">
                                    <p>Yakin menghapus <code>{{ $permission->name }}</code>?</p>

                                    @if ($permission->mappings_count > 0)
                                        <x-ui.alert variant="warning">
                                            {{ $permission->mappings_count }} resource key menunjuk permission ini.
                                            Key-nya tidak ikut terhapus, tapi berubah jadi tak terpetakan — dan aksesnya
                                            langsung tertutup untuk semua orang kecuali super admin.
                                        </x-ui.alert>
                                    @endif

                                    @if ($permission->roles_count > 0)
                                        <p class="text-ink-muted">
                                            {{ $permission->roles_count }} role kehilangan izin ini.
                                        </p>
                                    @endif

                                    <x-slot:footer>
                                        <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('modal-close', 'hapus-permission-{{ $permission->id }}')">Batal</x-ui.button>

                                        <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}">
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
                    <x-ui.empty-state title="Belum ada permission" />
                </td>
            </tr>
        @endforelse
        <x-slot:footer>{{ $permissions->links() }}</x-slot:footer>
    </x-ui.table>
</x-layouts.admin>
