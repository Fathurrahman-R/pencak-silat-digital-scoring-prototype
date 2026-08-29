@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Permission"
                 description="Izin mentah yang dibagikan ke role. Resource key menunjuk ke sini lewat pemetaan."
                 :breadcrumb="['Permission' => null]">
    <x-slot:actions>
        <x-can :resource="rk('permissions', ResourceAction::Create)">
            <x-si.tombol :tautan="route('admin.permissions.create')" ukuran="kecil" ikon="plus">
                Tambah permission
            </x-si.tombol>
        </x-can>
    </x-slot:actions>

    <x-si.tabel :table="$table"
                :selectable="$permissions->reject(fn ($permission) => $permission->is_locked)->pluck('id')->all()"
                :headers="['name' => 'Nama', 'group' => 'Grup', 0 => 'Dipetakan dari key', 1 => 'Role', 2 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari nama permission…"
                                :tampil="$permissions->count()" :total="$permissions->total()">
                <x-slot:filters>
                    <x-si.pilihan name="group" :selected="request('group')" :options="$groups"
                                  placeholder="Semua grup" aria-label="Saring menurut grup"
                                  class="w-[200px]" />
                </x-slot:filters>

                <x-slot:chips>
                    <x-si.saring param="status" semua="Semua"
                                 :pilihan="['dipakai' => 'Dipakai resource key', 'yatim' => 'Tidak dipakai']" />
                </x-slot:chips>

                <x-slot:bulk>
                    <x-can :resource="rk('permissions', ResourceAction::Delete)">
                        {{-- Sebelumnya tombol ini mengirim langsung, tanpa satu pun
                             konfirmasi dan tanpa menyebut berapa yang terpilih. --}}
                        <x-si.hapus-borongan :aksi="route('admin.permissions.bulk-destroy')"
                                             benda="permission"
                                             akibat="Resource key yang menunjuk permission ini jadi tidak terpetakan, dan pintu yang dijaganya tertutup untuk semua orang sampai dipetakan ulang." />
                    </x-can>
                </x-slot:bulk>
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($permissions as $permission)
            <x-si.tabel.baris :id="$permission->is_locked ? null : $permission->id">
                <x-si.tabel.sel header>
                    <div class="flex items-center gap-2">
                        <code>{{ $permission->name }}</code>

                        @if ($permission->is_locked)
                            <x-si.badge varian="netral" ikon="lock">Bawaan sistem</x-si.badge>
                        @endif
                    </div>

                    @if ($permission->label)
                        <span class="block text-[13px] font-normal text-ink-muted">{{ $permission->label }}</span>
                    @endif
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $permission->group ?: 'Tanpa grup' }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    @if ($permission->mappings_count === 0)
                        <x-si.badge varian="perhatian">Tidak dipakai key mana pun</x-si.badge>
                    @else
                        <div class="flex flex-wrap gap-1">
                            @foreach ($permission->mappings as $mapping)
                                <code class="rounded-[var(--radius-kecil)] bg-surface-inset px-1.5 py-0.5 font-mono text-[13px] text-ink">{{ $mapping->key() }}</code>
                            @endforeach
                        </div>
                    @endif
                </x-si.tabel.sel>

                <x-si.tabel.sel numeric>{{ $permission->roles_count }}</x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    {{-- Kata, bukan pensil dan tong sampah telanjang: `title`
                         tidak pernah muncul di layar sentuh. --}}
                    <div class="flex justify-end gap-1.5">
                        <x-can :resource="rk('permissions', ResourceAction::Update)">
                            <x-si.tombol :tautan="route('admin.permissions.edit', $permission)"
                                         varian="kedua" ukuran="kecil">
                                Ubah
                            </x-si.tombol>
                        </x-can>

                        @unless ($permission->is_locked)
                            <x-can :resource="rk('permissions', ResourceAction::Delete)">
                                {{-- Kedua angkanya dikirim bersama tombolnya: dialog
                                     yang menyebut "beberapa key" tidak memberi tahu
                                     apakah yang tertutup satu pintu atau dua belas. --}}
                                <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                             data-aksi="{{ route('admin.permissions.destroy', $permission) }}"
                                             data-nama="{{ $permission->name }}"
                                             data-rincian="{{ $permission->mappings_count }} resource key menunjuk permission ini, dan {{ $permission->roles_count }} role memegangnya."
                                             x-on:click="$dispatch('hapus-permission', $el.dataset)">
                                    Hapus
                                </x-si.tombol>
                            </x-can>
                        @endunless
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($permissions->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada permission"
                             syarat="Permission adalah izin mentah yang ditunjuk resource key. Buat satu lebih dulu, lalu petakan key ke sana lewat menu Pemetaan." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $permissions->links() }}</x-slot:footer>
    </x-si.tabel>

    <x-can :resource="rk('permissions', ResourceAction::Delete)">
        <x-si.hapus-baris benda="permission"
                          akibat="Key-nya sendiri tidak ikut terhapus, tapi berubah jadi tak terpetakan — dan pintu yang dijaganya langsung tertutup untuk semua orang kecuali super admin, sampai dipetakan ulang lewat menu Pemetaan." />
    </x-can>
</x-layouts.admin>
