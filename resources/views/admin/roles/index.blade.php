@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Role"
                 description="Sekumpulan permission yang bisa ditugaskan ke pengguna."
                 :breadcrumb="['Role' => null]">
    <x-slot:actions>
        <x-can :resource="rk('roles', ResourceAction::Create)">
            <x-si.tombol :tautan="route('admin.roles.create')" ukuran="kecil" ikon="plus">
                Tambah role
            </x-si.tombol>
        </x-can>
    </x-slot:actions>

    <x-si.tabel :table="$table"
                :selectable="$roles->reject(fn ($role) => $role->is_locked || $role->isSuperAdmin())->pluck('id')->all()"
                openable
                :headers="['name' => 'Nama', 0 => 'Label', 1 => 'Permission', 2 => 'Pengguna', 3 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari role…"
                                :tampil="$roles->count()" :total="$roles->total()">
                <x-slot:bulk>
                    <x-can :resource="rk('roles', ResourceAction::Delete)">
                        {{-- Sebelumnya tombol ini mengirim langsung, tanpa satu pun
                             konfirmasi dan tanpa menyebut berapa yang terpilih. --}}
                        <x-si.hapus-borongan :aksi="route('admin.roles.bulk-destroy')"
                                             benda="role"
                                             akibat="Setiap pengguna yang memegang role ini kehilangan izin yang dibawanya. Role terkunci dilewati, tapi sisanya terhapus permanen." />
                    </x-can>
                </x-slot:bulk>
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($roles as $role)
            <x-si.tabel.baris :id="$role->is_locked || $role->isSuperAdmin() ? null : $role->id"
                              :panel="route('admin.roles.panel', $role)">
                <x-si.tabel.sel header>
                    <div class="flex items-center gap-2">
                        {{ $role->name }}

                        {{-- Ungu tidak ada di palet mana pun; badge itu diam-diam
                             tampil abu-abu sama seperti role biasa. Yang
                             membedakannya sekarang kata dan perisainya. --}}
                        @if ($role->isSuperAdmin())
                            <x-si.badge varian="perhatian" ikon="shield">Super admin</x-si.badge>
                        @elseif ($role->is_locked)
                            <x-si.badge varian="netral" ikon="lock">Terkunci</x-si.badge>
                        @endif
                    </div>
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $role->label ?: 'Tanpa label' }}</x-si.tabel.sel>

                {{-- Super admin memegang seluruh permission tanpa didaftar satu
                     per satu, jadi angkanya memang tidak ada. Ditulis katanya. --}}
                <x-si.tabel.sel :numeric="! $role->isSuperAdmin()">
                    {{ $role->isSuperAdmin() ? 'Semua' : $role->permissions_count }}
                </x-si.tabel.sel>

                <x-si.tabel.sel numeric>{{ $role->users_count }}</x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    {{-- Kata, bukan pensil dan tong sampah telanjang: `title`
                         tidak pernah muncul di layar sentuh. --}}
                    <div class="flex justify-end gap-1.5" data-row-action>
                        <x-can :resource="rk('roles', ResourceAction::Update)">
                            <x-si.tombol :tautan="route('admin.roles.edit', $role)"
                                         varian="kedua" ukuran="kecil">
                                Ubah
                            </x-si.tombol>
                        </x-can>

                        @if (! $role->is_locked && ! $role->isSuperAdmin())
                            <x-can :resource="rk('roles', ResourceAction::Delete)">
                                <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                             data-aksi="{{ route('admin.roles.destroy', $role) }}"
                                             data-nama="{{ $role->name }}"
                                             data-rincian="{{ $role->users_count }} pengguna memegang role ini."
                                             x-on:click="$dispatch('hapus-role', $el.dataset)">
                                    Hapus
                                </x-si.tombol>
                            </x-can>
                        @endif
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($roles->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada role"
                             syarat="Role adalah sekumpulan permission yang dipakai bersama. Buat satu lebih dulu lewat tombol di kanan atas, lalu tugaskan ke pengguna." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $roles->links() }}</x-slot:footer>
    </x-si.tabel>

    <x-can :resource="rk('roles', ResourceAction::Delete)">
        <x-si.hapus-baris benda="role"
                          akibat="Mereka kehilangan seluruh izin yang dibawa role ini seketika — termasuk yang sedang membuka panel gelanggang. Izin dari role lain yang mereka pegang tetap berlaku." />
    </x-can>

    <x-si.panel-rincian judul="Detail role" />
</x-layouts.admin>
