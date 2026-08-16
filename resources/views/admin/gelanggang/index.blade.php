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

    <div class="space-y-6">
        <x-ui.alert variant="info" title="Kode gelanggang dipakai di alamat siaran">
            Kode inilah yang muncul di alamat halaman siaran langsung dan overlay vMix, jadi
            sebaiknya pendek dan tidak diubah lagi setelah kejuaraan berjalan.
        </x-ui.alert>

        <x-ui.card>
            @forelse ($arenas as $arena)
                <div class="flex items-center gap-4 border-b border-line py-3 last:border-0">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-2 font-mono text-sm text-ink">
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
                            <x-ui.button type="button" variant="secondary" size="xs" title="Ubah"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-ubah-{{ $arena->id }}')">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>

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
                            <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                         x-on:click="$dispatch('modal-open', 'gelanggang-hapus-{{ $arena->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>

                            <x-ui.modal :id="'gelanggang-hapus-'.$arena->id" title="Hapus gelanggang" size="sm">
                                Yakin menghapus <strong>{{ $arena->name }}</strong>?

                                <x-slot:footer>
                                    <x-ui.button variant="secondary" type="button"
                                                 x-on:click="$dispatch('modal-close', 'gelanggang-hapus-{{ $arena->id }}')">Batal</x-ui.button>

                                    <form method="POST" action="{{ route('admin.turnamen.gelanggang.destroy', [$tournament, $arena]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" type="submit">Hapus</x-ui.button>
                                    </form>
                                </x-slot:footer>
                            </x-ui.modal>
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
</x-layouts.admin>
