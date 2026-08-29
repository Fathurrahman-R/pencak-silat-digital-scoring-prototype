@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Pemetaan resource key"
                 description="Menentukan permission mana yang berada di balik tiap resource key. Mengubahnya berlaku seketika di route, tampilan, policy, dan menu — tanpa menyentuh kode."
                 :breadcrumb="['Pemetaan Key' => null]">
    <x-slot:actions>
        <x-can :resource="rk('mappings', ResourceAction::Update)">
            @if ($unmappedCount > 0)
                <form method="POST" action="{{ route('admin.mappings.auto') }}">
                    @csrf
                    <x-si.tombol tipe="submit" ukuran="kecil" ikon="link">
                        Petakan otomatis {{ $unmappedCount }} key kosong
                    </x-si.tombol>
                </form>
            @endif
        </x-can>
    </x-slot:actions>

    @if ($unmappedCount > 0)
        <x-si.callout varian="perhatian" class="mb-4">
            {{ $unmappedCount }} key belum menunjuk permission mana pun. Selama masih kosong, aksesnya tertutup untuk
            semua orang kecuali super admin.
        </x-si.callout>
    @endif

    <x-si.tabel :table="$table" :headers="[0 => 'Resource key', 'action' => 'Aksi', 1 => 'Permission', 2 => '']">
        <x-slot:toolbar>
            <x-si.tabel.toolbar :table="$table" placeholder="Cari key atau permission…">
                <x-slot:filters>
                    <x-si.pilihan name="status" :selected="request('status')"
                                  placeholder="Semua"
                                  :options="['mapped' => 'Sudah dipetakan', 'unmapped' => 'Belum dipetakan']"
                                  aria-label="Saring menurut keadaan pemetaan" class="w-[200px]" />
                </x-slot:filters>
            </x-si.tabel.toolbar>
        </x-slot:toolbar>

        @foreach ($mappings as $mapping)
            <x-si.tabel.baris>
                <x-si.tabel.sel header>
                    <code>{{ $mapping->key() }}</code>
                </x-si.tabel.sel>

                <x-si.tabel.sel>{{ $mapping->action->label() }}</x-si.tabel.sel>

                <x-si.tabel.sel>
                    <x-can :resource="rk('mappings', ResourceAction::Update)">
                        {{-- Select diberi lebar minimum: sebagai anak flex ia
                             mau menyusut sampai tinggal kotak selebar panahnya,
                             dan nama permission yang panjang jadi tak terbaca
                             sama sekali. --}}
                        <form method="POST" action="{{ route('admin.mappings.update', $mapping) }}"
                              class="flex flex-wrap items-center gap-2">
                            @csrf
                            @method('PUT')

                            {{-- Pilihan kosongnya menyebut AKIBATNYA, bukan
                                 sekadar "tidak dipetakan": key tanpa permission
                                 menutup pintunya untuk semua orang. --}}
                            <x-si.pilihan name="permission_id"
                                          :selected="$mapping->permission_id"
                                          placeholder="Tidak dipetakan — akses tertutup"
                                          :options="$permissions"
                                          :id="'permission-'.$mapping->id"
                                          aria-label="Permission untuk {{ $mapping->key() }}"
                                          class="min-w-[260px] flex-1" />

                            <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">Simpan</x-si.tombol>
                        </form>
                    </x-can>

                    {{-- Tanpa izin mengubah, pemetaannya hanya ditampilkan. --}}
                    @unless (resource_allows(rk('mappings', ResourceAction::Update)))
                        @if ($mapping->isMapped())
                            <code>{{ $mapping->permission->name }}</code>
                        @else
                            <x-si.badge varian="bahaya">belum dipetakan</x-si.badge>
                        @endif
                    @endunless
                </x-si.tabel.sel>

                <x-si.tabel.sel align="right">
                    @if ($mapping->isMapped())
                        <x-can :resource="rk('mappings', ResourceAction::Update)">
                            <form method="POST" action="{{ route('admin.mappings.destroy', $mapping) }}">
                                @csrf
                                @method('DELETE')
                                <x-si.tombol tipe="submit" varian="kedua" ukuran="kecil">
                                    Lepas pemetaan
                                </x-si.tombol>
                            </form>
                        </x-can>
                    @endif
                </x-si.tabel.sel>
            </x-si.tabel.baris>
        @endforeach

        @if ($mappings->isEmpty())
            <x-slot:kosong>
                <x-si.kosong judul="Belum ada pemetaan"
                             syarat="Pemetaan dibuat sendiri begitu Anda membuat resource lewat menu Resource. Kalau daftar ini kosong padahal resource sudah ada, kosongkan penyaring di atas." />
            </x-slot:kosong>
        @endif
        <x-slot:footer>{{ $mappings->links() }}</x-slot:footer>
    </x-si.tabel>
</x-layouts.admin>
