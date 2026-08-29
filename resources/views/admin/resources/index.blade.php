@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Resource"
                 description="Setiap resource menghasilkan resource key berbentuk {resource}.{aksi} yang dipakai di route, tampilan, dan menu."
                 :breadcrumb="['Resource' => null]">
    <x-slot:actions>
        <x-can :resource="rk('resources', ResourceAction::Create)">
            <x-si.tombol :tautan="route('admin.resources.create')" ukuran="kecil" ikon="plus">
                Tambah resource
            </x-si.tombol>
        </x-can>
    </x-slot:actions>

    <x-si.tabel :table="$table"
                :selectable="$resources->reject(fn ($resource) => $resource->is_locked)->pluck('id')->all()"
                :headers="['key' => 'Key', 'label' => 'Label', 'group' => 'Grup', 0 => 'Aksi', 1 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari resource…"
                                :tampil="$resources->count()" :total="$resources->total()">
                <x-slot:filters>
                    <x-si.pilihan name="group" :selected="request('group')" :options="$groups"
                                  placeholder="Semua grup" aria-label="Saring menurut grup"
                                  class="w-[200px]" />
                </x-slot:filters>

                <x-slot:bulk>
                    <x-can :resource="rk('resources', ResourceAction::Delete)">
                        {{-- Sebelumnya tombol ini mengirim langsung, tanpa satu pun
                             konfirmasi dan tanpa menyebut berapa yang terpilih. --}}
                        <x-si.hapus-borongan :aksi="route('admin.resources.bulk-destroy')"
                                             benda="resource"
                                             akibat="Permission miliknya tidak ikut terhapus, tapi key yang dipakai kode untuk menjaga pintunya hilang — dan pintu tanpa key tertutup untuk semua orang." />
                    </x-can>
                </x-slot:bulk>
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($resources as $resource)
            <x-si.tabel.baris :id="$resource->is_locked ? null : $resource->id">
                <x-si.tabel.sel header>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.resources.show', $resource) }}" class="underline underline-offset-2">
                            <code>{{ $resource->key }}</code>
                        </a>

                        @if ($resource->is_locked)
                            <x-si.badge varian="netral" ikon="lock">Bawaan sistem</x-si.badge>
                        @endif
                    </div>
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $resource->label }}</x-si.tabel.sel>
                <x-si.tabel.sel>{{ $resource->group ?: 'Tanpa grup' }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    @php($belumDipetakan = $resource->mappings->whereNull('permission_id')->count())

                    <div class="flex flex-wrap items-center gap-1">
                        <x-si.badge varian="netral">{{ $resource->mappings_count }} aksi</x-si.badge>

                        @if ($belumDipetakan > 0)
                            <x-si.badge varian="bahaya">{{ $belumDipetakan }} belum dipetakan</x-si.badge>
                        @endif
                    </div>
                </x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    {{-- Kata, bukan mata, pensil, dan tong sampah telanjang.
                         Tiga gambar kecil berjajar tanpa nama menuntut ditebak,
                         dan yang paling kanan menghapus. --}}
                    <div class="flex justify-end gap-1.5">
                        <x-si.tombol :tautan="route('admin.resources.show', $resource)"
                                     varian="kedua" ukuran="kecil">
                            Rincian
                        </x-si.tombol>

                        <x-can :resource="rk('resources', ResourceAction::Update)">
                            <x-si.tombol :tautan="route('admin.resources.edit', $resource)"
                                         varian="kedua" ukuran="kecil">
                                Ubah
                            </x-si.tombol>
                        </x-can>

                        @unless ($resource->is_locked)
                            <x-can :resource="rk('resources', ResourceAction::Delete)">
                                <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                             data-aksi="{{ route('admin.resources.destroy', $resource) }}"
                                             data-nama="{{ $resource->key }}"
                                             data-rincian="{{ $resource->mappings_count }} pemetaan aksinya ikut terhapus."
                                             x-on:click="$dispatch('hapus-resource', $el.dataset)">
                                    Hapus
                                </x-si.tombol>
                            </x-can>
                        @endunless
                    </div>
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($resources->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada resource"
                             syarat="Resource menghasilkan key berbentuk {resource}.{aksi} yang dipakai route dan menu untuk menjaga pintunya. Buat yang pertama lewat tombol di kanan atas." />
            </x-slot:kosong>
        @endif

        <x-slot:footer>{{ $resources->links() }}</x-slot:footer>
    </x-si.tabel>

    <x-can :resource="rk('resources', ResourceAction::Delete)">
        <x-si.hapus-baris benda="resource"
                          akibat="Permission-nya TIDAK ikut terhapus — bisa jadi masih dipakai key lain. Yang hilang adalah key yang dipakai kode untuk menjaga pintunya, dan pintu tanpa key tertutup untuk semua orang kecuali super admin." />
    </x-can>
</x-layouts.admin>
