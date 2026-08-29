@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Pengguna"
                 description="Kelola akun dan role yang dimilikinya."
                 :breadcrumb="['Pengguna' => null]">
    <x-slot:actions>
        <x-can :resource="rk('users', ResourceAction::Export)">
            <x-ui.button :href="route('admin.users.export', request()->query())" variant="secondary" size="sm">
                <x-ui.icon name="download" class="h-4 w-4" />
                Ekspor CSV
            </x-ui.button>
        </x-can>

        <x-can :resource="rk('users', ResourceAction::Create)">
            <x-ui.button :href="route('admin.users.create')" size="sm">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah pengguna
            </x-ui.button>
        </x-can>
    </x-slot:actions>

    <x-ui.table :table="$table"
                :selectable="$users->pluck('id')->all()"
                openable
                :headers="['name' => 'Nama', 'email' => 'Email', 0 => 'Role', 1 => 'Status', 'created_at' => 'Dibuat', 2 => '']">
        <x-slot:toolbar>
            <x-ui.table.toolbar :table="$table" placeholder="Cari nama atau email…">
                <x-slot:filters>
                    <select name="role" class="form-select">
                        <option value="">Semua role</option>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-slot:filters>

                <x-slot:chips>
                    <x-ui.filter-chips param="status"
                                       all="Semua status"
                                       :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
                </x-slot:chips>

                <x-slot:bulk>
                    <x-can :resource="rk('users', ResourceAction::Delete)">
                        {{-- Sebelumnya tombol ini mengirim langsung, tanpa satu pun
                             konfirmasi dan tanpa menyebut berapa yang terpilih. --}}
                        <x-si.hapus-borongan :aksi="route('admin.users.bulk-destroy')"
                                             benda="pengguna"
                                             akibat="Akun yang terhapus kehilangan seluruh akses seketika, termasuk yang sedang membuka panel gelanggang. Penugasannya sebagai wasit atau juri di partai yang sudah dijadwalkan ikut kosong." />
                    </x-can>
                </x-slot:bulk>
            </x-ui.table.toolbar>
        </x-slot:toolbar>

        @forelse ($users as $user)
            <x-ui.table.row :id="$user->id" :panel="route('admin.users.panel', $user)">
                <x-ui.table.cell header>
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :user="$user" size="sm" />
                        {{ $user->name }}
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $user->email }}</x-ui.table.cell>

                <x-ui.table.cell>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($user->roles as $role)
                            <x-ui.badge :variant="$role->isSuperAdmin() ? 'purple' : 'primary'">{{ $role->displayName() }}</x-ui.badge>
                        @empty
                            <span class="text-ink-muted">—</span>
                        @endforelse
                    </div>
                </x-ui.table.cell>

                <x-ui.table.cell>
                    <x-ui.badge :variant="$user->is_active ? 'success' : 'danger'" dot>
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-ui.badge>
                </x-ui.table.cell>

                <x-ui.table.cell>{{ $user->created_at?->translatedFormat('d M Y') }}</x-ui.table.cell>

                <x-ui.table.cell align="right">
                    <div class="flex justify-end gap-1" data-row-action>
                        <x-can :resource="rk('users', ResourceAction::Update)">
                            <x-ui.button :href="route('admin.users.edit', $user)" variant="secondary" size="xs" title="Ubah">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        </x-can>

                        <x-can :resource="rk('users', ResourceAction::Delete)">
                            <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                         x-on:click="$dispatch('modal-open', 'hapus-user-{{ $user->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>

                            <x-ui.modal :id="'hapus-user-'.$user->id" title="Hapus pengguna" size="sm">
                                Yakin menghapus <strong>{{ $user->name }}</strong>? Tindakan ini tidak bisa dibatalkan.

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('modal-close', 'hapus-user-{{ $user->id }}')">Batal</x-ui.button>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" type="submit">Hapus</x-ui.button>
                                    </form>
                                </x-slot:footer>
                            </x-ui.modal>
                        </x-can>
                    </div>
                </x-ui.table.cell>
            </x-ui.table.row>
        @empty
            <tr>
                <td colspan="8">
                    <x-ui.empty-state title="Tidak ada pengguna" description="Ubah kata kunci pencarian atau tambahkan pengguna baru." />
                </td>
            </tr>
        @endforelse
        <x-slot:footer>{{ $users->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.drawer-remote title="Detail pengguna" />
</x-layouts.admin>
