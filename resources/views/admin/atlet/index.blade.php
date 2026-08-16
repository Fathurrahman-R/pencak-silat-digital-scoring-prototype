@php
    use App\Enums\JenisBerkas;
    use App\Enums\JenisKelamin;
    use App\Enums\ResourceAction;
@endphp

<x-layouts.admin heading="Atlet"
                 :description="$contingent->name.' · '.$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Kontingen' => route('admin.turnamen.kontingen.index', $tournament),
                     $contingent->name => null,
                 ]">
    <x-slot:actions>
        @resource(rk('atlet', ResourceAction::Create))
            <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'atlet-baru')">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Tambah atlet
            </x-ui.button>
        @endresource
    </x-slot:actions>

    @include('admin.kontingen.tabs')

    <div class="space-y-6">
        <form method="GET" class="max-w-sm">
            <x-ui.input name="q" label="" :value="request('q')" placeholder="Cari nama atlet…" />
        </form>

        <x-ui.card>
            @forelse ($athletes as $athlete)
                @php
                    $golongan = $athlete->golonganUsia($tournament);
                    $kurang = $athlete->berkasKurang($tournament);
                @endphp

                <div class="flex flex-wrap items-start gap-4 border-b border-line py-4 last:border-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">{{ $athlete->name }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $athlete->jenis_kelamin->label() }} ·
                            {{ $athlete->birth_date->translatedFormat('d M Y') }} ·
                            {{ $athlete->umurSaatKejuaraan($tournament) }} tahun saat kejuaraan
                            @if ($athlete->weight_claim)
                                · {{ $athlete->weight_claim }} kg
                            @endif
                        </p>
                    </div>

                    <div class="min-w-[140px]">
                        @if ($golongan)
                            <x-ui.badge variant="neutral">{{ $golongan->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="danger">Di luar golongan</x-ui.badge>
                        @endif
                    </div>

                    {{--
                        Kelengkapan berkas ditampilkan per atlet, bukan sebagai
                        satu angka di halaman kontingen. Yang perlu diperbaiki
                        official adalah berkas milik orang tertentu, dan daftar
                        wajibnya pun berbeda antar atlet.
                    --}}
                    <div class="min-w-[200px]">
                        @if ($kurang === [])
                            <x-ui.badge variant="success">Berkas lengkap</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning">Kurang {{ count($kurang) }} berkas</x-ui.badge>
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ implode(', ', array_map(fn (JenisBerkas $j) => $j->label(), $kurang)) }}
                            </p>
                        @endif
                    </div>

                    <div class="flex gap-1">
                        @resource(rk('atlet', ResourceAction::Update))
                            <x-ui.button type="button" variant="secondary" size="xs" title="Berkas"
                                         x-on:click="$dispatch('modal-open', 'berkas-{{ $athlete->id }}')">
                                <x-ui.icon name="paperclip" class="h-4 w-4" />
                            </x-ui.button>

                            <x-ui.button type="button" variant="secondary" size="xs" title="Ubah"
                                         x-on:click="$dispatch('modal-open', 'atlet-ubah-{{ $athlete->id }}')">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                            </x-ui.button>
                        @endresource

                        @resource(rk('atlet', ResourceAction::Delete))
                            <x-ui.button type="button" variant="secondary" size="xs" title="Hapus"
                                         x-on:click="$dispatch('modal-open', 'atlet-hapus-{{ $athlete->id }}')">
                                <x-ui.icon name="trash-2" class="h-4 w-4 text-danger" />
                            </x-ui.button>
                        @endresource
                    </div>
                </div>

                @resource(rk('atlet', ResourceAction::Update))
                    <x-ui.modal :id="'atlet-ubah-'.$athlete->id" title="Ubah atlet" size="md">
                        <form method="POST" id="ubah-atlet-{{ $athlete->id }}"
                              action="{{ route('admin.turnamen.kontingen.atlet.update', [$tournament, $contingent, $athlete]) }}"
                              class="space-y-4">
                            @csrf
                            @method('PUT')

                            @include('admin.atlet.form', ['athlete' => $athlete, 'suffix' => $athlete->id])
                        </form>

                        <x-slot:footer>
                            <x-ui.button variant="secondary" type="button"
                                         x-on:click="$dispatch('modal-close', 'atlet-ubah-{{ $athlete->id }}')">Batal</x-ui.button>
                            <x-ui.button type="submit" form="ubah-atlet-{{ $athlete->id }}">Simpan</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>

                    <x-ui.modal :id="'berkas-'.$athlete->id" :title="'Berkas '.$athlete->name" size="lg">
                        <div class="space-y-5">
                            @foreach ($athlete->documents as $document)
                                <div class="flex items-center gap-3 border-b border-line pb-3">
                                    <x-ui.icon name="file-text" class="size-5 shrink-0 text-ink-muted" />

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-base2 text-ink">{{ $document->jenis->label() }}</p>
                                        <p class="truncate text-xs text-ink-muted">
                                            {{ $document->original_name }} · {{ $document->ukuran() }}
                                        </p>
                                    </div>

                                    <x-ui.button size="xs" variant="secondary"
                                                 :href="route('admin.turnamen.kontingen.atlet.berkas.show', [$tournament, $contingent, $athlete, $document])"
                                                 target="_blank">
                                        Lihat
                                    </x-ui.button>

                                    <form method="POST"
                                          action="{{ route('admin.turnamen.kontingen.atlet.berkas.destroy', [$tournament, $contingent, $athlete, $document]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" size="xs" variant="secondary" title="Hapus berkas">
                                            <x-ui.icon name="trash-2" class="size-4 text-danger" />
                                        </x-ui.button>
                                    </form>
                                </div>
                            @endforeach

                            <form method="POST"
                                  action="{{ route('admin.turnamen.kontingen.atlet.berkas.store', [$tournament, $contingent, $athlete]) }}"
                                  enctype="multipart/form-data" class="space-y-4">
                                @csrf

                                <x-ui.select name="jenis" label="Jenis berkas"
                                             :id="'jenis-berkas-'.$athlete->id"
                                             :options="collect(JenisBerkas::cases())
                                                 ->mapWithKeys(fn (JenisBerkas $j) => [$j->value => $j->label()])
                                                 ->all()" required />

                                <x-ui.file-upload name="berkas" label="Berkas" required
                                                  :id="'berkas-file-'.$athlete->id"
                                                  accept=".jpg,.jpeg,.png,.pdf"
                                                  hint="JPG, PNG, atau PDF. Paling besar 4 MB. Mengunggah jenis yang sama akan menggantikan berkas sebelumnya." />

                                <x-ui.button type="submit" size="sm">Unggah</x-ui.button>
                            </form>

                            <div class="rounded-lg bg-surface-inset p-3 text-xs text-ink-muted">
                                Berkas wajib untuk atlet ini:
                                {{ implode(', ', array_map(fn (JenisBerkas $j) => $j->label(), $athlete->berkasWajib($tournament))) }}.
                            </div>
                        </div>

                        <x-slot:footer>
                            <x-ui.button variant="secondary" type="button"
                                         x-on:click="$dispatch('modal-close', 'berkas-{{ $athlete->id }}')">Tutup</x-ui.button>
                        </x-slot:footer>
                    </x-ui.modal>
                @endresource

                @resource(rk('atlet', ResourceAction::Delete))
                    <x-ui.modal :id="'atlet-hapus-'.$athlete->id" title="Hapus atlet" size="sm">
                        Yakin menghapus <strong>{{ $athlete->name }}</strong>? Berkas dan pendaftarannya ikut terhapus.

                        <x-slot:footer>
                            <x-ui.button variant="secondary" type="button"
                                         x-on:click="$dispatch('modal-close', 'atlet-hapus-{{ $athlete->id }}')">Batal</x-ui.button>

                            <form method="POST" action="{{ route('admin.turnamen.kontingen.atlet.destroy', [$tournament, $contingent, $athlete]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="danger" type="submit">Hapus</x-ui.button>
                            </form>
                        </x-slot:footer>
                    </x-ui.modal>
                @endresource
            @empty
                <x-ui.empty-state title="Belum ada atlet"
                                  description="Golongan usia dihitung sendiri dari tanggal lahir terhadap tanggal kejuaraan dimulai." />
            @endforelse
        </x-ui.card>

        {{ $athletes->links() }}
    </div>

    @resource(rk('atlet', ResourceAction::Create))
        <x-ui.modal id="atlet-baru" title="Tambah atlet" size="md">
            <form method="POST" id="atlet-baru-form"
                  action="{{ route('admin.turnamen.kontingen.atlet.store', [$tournament, $contingent]) }}"
                  class="space-y-4">
                @csrf

                @include('admin.atlet.form', ['athlete' => null, 'suffix' => 'baru'])
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" type="button"
                             x-on:click="$dispatch('modal-close', 'atlet-baru')">Batal</x-ui.button>
                <x-ui.button type="submit" form="atlet-baru-form">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endresource
</x-layouts.admin>
