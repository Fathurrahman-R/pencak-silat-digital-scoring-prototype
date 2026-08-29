@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Pengguna"
                 description="Kelola akun dan role yang dimilikinya."
                 :breadcrumb="['Pengguna' => null]">
    <x-slot:actions>
        <x-can :resource="rk('users', ResourceAction::Export)">
            <x-si.tombol :tautan="route('admin.users.export', request()->query())"
                         varian="kedua" ukuran="kecil" ikon="download">
                Ekspor CSV
            </x-si.tombol>
        </x-can>

        <x-can :resource="rk('users', ResourceAction::Create)">
            <x-si.tombol :tautan="route('admin.users.create')" ukuran="kecil" ikon="plus">
                Tambah pengguna
            </x-si.tombol>
        </x-can>
    </x-slot:actions>

    <x-si.tabel :table="$table"
                :selectable="$users->pluck('id')->all()"
                openable
                :headers="['name' => 'Nama', 'email' => 'Email', 0 => 'Role', 1 => 'Status', 'created_at' => 'Dibuat', 2 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari nama atau email…"
                                :tampil="$users->count()" :total="$users->total()">
                <x-slot:filters>
                    {{-- Tanpa label tampak, namanya tetap harus ada: select
                         telanjang hanya dibacakan sebagai "kotak pilihan". --}}
                    <x-si.pilihan name="role" :selected="request('role')"
                                  :options="$roles" placeholder="Semua role"
                                  aria-label="Saring menurut role" class="w-[200px]" />
                </x-slot:filters>

                <x-slot:chips>
                    <x-si.saring param="status" semua="Semua status"
                                 :pilihan="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
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
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($users as $user)
            <x-si.tabel.baris :id="$user->id" :panel="route('admin.users.panel', $user)">
                <x-si.tabel.sel header>
                    <div class="flex items-center gap-3">
                        <x-si.foto :user="$user" ukuran="kecil" />
                        {{ $user->name }}
                    </div>
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $user->email }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($user->roles as $role)
                            {{-- Super admin dibedakan kata, bukan rona ungu yang
                                 tidak ada di palet mana pun dan diam-diam jatuh
                                 ke abu-abu. --}}
                            <x-si.badge :varian="$role->isSuperAdmin() ? 'perhatian' : 'netral'"
                                        :ikon="$role->isSuperAdmin() ? 'shield' : null">
                                {{ $role->displayName() }}
                            </x-si.badge>
                        @empty
                            <span class="text-ink-muted">Tanpa role</span>
                        @endforelse
                    </div>
                </x-si.tabel.sel>

                <x-si.tabel.sel>
                    <x-si.badge :varian="$user->is_active ? 'sukses' : 'bahaya'">
                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-si.badge>
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $user->created_at?->translatedFormat('d M Y') }}</x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    {{-- Kata, bukan pensil dan tong sampah telanjang: `title`
                         hanya muncul saat kursor berdiam di atasnya, dan di
                         layar sentuh tidak pernah muncul sama sekali. Yang
                         tersisa di sana dua gambar kecil yang harus ditebak,
                         dan salah satunya menghapus akun. --}}
                    <div class="flex justify-end gap-1.5" data-row-action>
                        <x-can :resource="rk('users', ResourceAction::Update)">
                            <x-si.tombol :tautan="route('admin.users.edit', $user)"
                                         varian="kedua" ukuran="kecil">
                                Ubah
                            </x-si.tombol>
                        </x-can>

                        <x-can :resource="rk('users', ResourceAction::Delete)">
                            {{-- Muatan lewat data-*: tanda kutip di dalam JSON
                                 memutus pembacaan ekspresi atribut. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.users.destroy', $user) }}"
                                         data-nama="{{ $user->name }}"
                                         x-on:click="$dispatch('hapus-pengguna', $el.dataset)">
                                Hapus
                            </x-si.tombol>
                        </x-can>
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($users->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Tidak ada pengguna"
                             syarat="Kosongkan penyaring di atas, atau tambahkan pengguna baru lewat tombol di kanan atas." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $users->links() }}</x-slot:footer>
    </x-si.tabel>

    {{-- SATU dialog hapus untuk seluruh halaman, bukan satu per baris.
         Sebelumnya tiap baris menanam modalnya sendiri, dan kalimatnya hanya
         "Yakin menghapus? Tindakan ini tidak bisa dibatalkan" — tidak ada
         satu pun kata tentang apa yang ikut hilang. --}}
    <x-can :resource="rk('users', ResourceAction::Delete)">
        <x-si.hapus-baris benda="pengguna"
                          akibat="Akun ini kehilangan seluruh akses seketika, termasuk kalau sedang membuka panel gelanggang. Penugasannya sebagai wasit atau juri di partai yang sudah dijadwalkan ikut kosong dan harus diisi ulang." />
    </x-can>

    <x-si.panel-rincian judul="Detail pengguna" />
</x-layouts.admin>
