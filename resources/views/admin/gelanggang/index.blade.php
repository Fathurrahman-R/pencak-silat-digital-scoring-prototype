@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Gelanggang"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Gelanggang' => null,
                 ]">
    <x-slot:actions>
        @resource(rk('gelanggang', ResourceAction::Create))
            <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'gelanggang-baru')">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah gelanggang
            </x-ui.button>
        @endresource
    </x-slot:actions>

    <div class="space-y-4">
        <x-ui.alert variant="info" title="Kode gelanggang dipakai di alamat siaran">
            Kode inilah yang muncul di alamat halaman siaran langsung dan overlay vMix, jadi
            sebaiknya pendek dan tidak diubah lagi setelah kejuaraan berjalan.
        </x-ui.alert>

        <x-ui.card>
            @forelse ($arenas as $arena)
                <div class="flex items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-inset font-mono text-sm text-ink">
                        {{ $arena->code }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink">{{ $arena->name }}</p>
                        <p class="text-xs text-ink-muted">Urutan {{ $arena->sort_order }}</p>
                    </div>

                    <x-ui.badge :variant="$arena->is_active ? 'success' : 'neutral'">
                        {{ $arena->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-ui.badge>

                    <div class="flex gap-1">
                        @resource(rk('gelanggang', ResourceAction::Update))
                            {{-- Kata, bukan pensil telanjang: tooltip tidak pernah
                                 muncul di layar sentuh. --}}
                            <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-ubah-{{ $arena->id }}')">
                                Ubah
                            </x-si.tombol>

                            <x-ui.modal :id="'gelanggang-ubah-'.$arena->id" title="Ubah gelanggang" size="sm">
                                <form method="POST" action="{{ route('admin.turnamen.gelanggang.update', [$tournament, $arena]) }}"
                                      id="ubah-gelanggang-{{ $arena->id }}" class="space-y-4">
                                    @csrf
                                    @method('PUT')

                                    <x-ui.input name="name" label="Nama gelanggang" :value="$arena->name" required
                                                :id="'nama-'.$arena->id" />
                                    <x-ui.input name="code" label="Kode" :value="$arena->code" required
                                                :id="'kode-'.$arena->id" />
                                    <x-ui.input type="number" name="sort_order" label="Urutan" :value="$arena->sort_order"
                                                :id="'urutan-'.$arena->id" />
                                    <x-ui.toggle name="is_active" label="Aktif" :checked="$arena->is_active"
                                                 :id="'aktif-'.$arena->id" />
                                </form>

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-ubah-{{ $arena->id }}')">Batal</x-ui.button>
                                    <x-ui.button type="submit" form="ubah-gelanggang-{{ $arena->id }}">Simpan</x-ui.button>
                                </x-slot:footer>
                            </x-ui.modal>
                        @endresource

                        @resource(rk('gelanggang', ResourceAction::Delete))
                            {{-- Muatan lewat data-*: tanda kutip di dalam JSON
                                 memutus pembacaan ekspresi atribut. --}}
                            <x-si.tombol tipe="button" varian="bahaya" ukuran="kecil"
                                         data-aksi="{{ route('admin.turnamen.gelanggang.destroy', [$tournament, $arena]) }}"
                                         data-nama="{{ $arena->name }}"
                                         data-partai="{{ $arena->matches()->count() }}"
                                         x-on:click="$dispatch('hapus-gelanggang', $el.dataset)">
                                Hapus
                            </x-si.tombol>
                        @endresource
                    </div>
                </div>
            @empty
                <x-ui.empty-state title="Belum ada gelanggang"
                                  description="Satu kejuaraan dapat menjalankan beberapa gelanggang sekaligus, masing-masing dengan wasit juri dan papan skornya sendiri." />
            @endforelse
        </x-ui.card>
    </div>

    @resource(rk('gelanggang', ResourceAction::Create))
        <x-ui.modal id="gelanggang-baru" title="Tambah gelanggang" size="sm">
            <form method="POST" action="{{ route('admin.turnamen.gelanggang.store', $tournament) }}"
                  id="gelanggang-baru-form" class="space-y-4">
                @csrf

                <x-ui.input name="name" label="Nama gelanggang" required
                            hint="Mis. Gelanggang 1." />
                <x-ui.input name="code" label="Kode" required
                            hint="Huruf, angka, dan tanda hubung. Mis. G1." />
                <x-ui.toggle name="is_active" label="Aktif" checked />
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button"
                             x-on:click="$dispatch('modal-close', 'gelanggang-baru')">Batal</x-ui.button>
                <x-ui.button type="submit" form="gelanggang-baru-form">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endresource

    {{--
        SATU dialog hapus untuk seluruh halaman, bukan satu per gelanggang.

        Menghapus gelanggang bukan tindakan kecil: partai yang dijadwalkan di
        sana kehilangan tempatnya, dan alamat overlay siaran yang sudah dipasang
        di vMix ikut mati. Keduanya disebut, beserta jumlah partainya —
        "yakin menghapus?" tidak memberi tahu apa pun tentang itu.
    --}}
    @resource(rk('gelanggang', ResourceAction::Delete))
        <div x-data="{ terbuka: false, aksi: '', nama: '', partai: 0 }"
             x-on:hapus-gelanggang.window="aksi = $event.detail.aksi; nama = $event.detail.nama;
                                           partai = $event.detail.partai; terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[460px] rounded-[var(--radius)] border border-danger bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-danger uppercase">Tidak bisa dibatalkan</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Hapus gelanggang <span x-text="nama"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        <span x-show="partai > 0">
                            <span class="font-semibold text-ink" x-text="partai"></span> partai kehilangan
                            tempatnya dan harus dijadwalkan ulang.
                        </span>
                        Alamat overlay siaran gelanggang ini ikut mati — kalau sudah dipasang di vMix,
                        sumbernya berhenti mengirim gambar.
                    </p>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit" varian="bahaya-tegas">Hapus gelanggang</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
