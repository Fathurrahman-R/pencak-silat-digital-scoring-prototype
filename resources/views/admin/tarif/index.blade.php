@php use App\Enums\ResourceAction; @endphp

@php($terkunci = ! $tournament->status->bolehUbahAturan())

<x-layouts.admin heading="Tarif pendaftaran"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Tarif' => null,
                 ]">
    <div class="space-y-4">
        @if ($terkunci)
            <x-ui.alert variant="warning" title="Tarif terkunci">
                Kejuaraan sudah {{ strtolower($tournament->status->label()) }}. Mengubah tarif sekarang
                berarti dua kontingen membayar harga berbeda untuk nomor yang sama, dan yang membayar
                lebih dulu tidak punya cara mengetahuinya.
            </x-ui.alert>
        @else
            <x-ui.alert variant="info" title="Tarif khusus mengalahkan tarif umum">
                Tulis satu tarif umum tanpa kategori dan golongan, lalu tambahkan pengecualian
                seperlunya. Baris yang menyebut golongan usia mengalahkan yang mengosongkannya, dan
                yang menyebut kategori mengalahkan yang tidak.
            </x-ui.alert>
        @endif

        <x-ui.card title="Biaya per nomor">
            @forelse ($tarifNomor as $tarif)
                <div class="flex items-center gap-4 border-b border-line py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-base2 text-ink">{{ $tarif->keterangan() }}</p>
                    </div>

                    <p class="silat-angka font-mono text-base2 text-ink">{{ $tarif->rupiah() }}</p>

                    @unless ($terkunci)
                        @resource(rk('tarif', ResourceAction::Update))
                            <form method="POST" action="{{ route('admin.turnamen.tarif.destroy', [$tournament, $tarif]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="secondary" size="xs" title="Hapus tarif">
                                    <x-ui.icon name="trash-2" class="size-4 text-danger" />
                                </x-ui.button>
                            </form>
                        @endresource
                    @endunless
                </div>
            @empty
                <x-ui.empty-state title="Belum ada tarif"
                                  description="Tanpa tarif, tagihan kontingen akan bernilai nol dan tidak bisa dikunci." />
            @endforelse

            @unless ($terkunci)
                @resource(rk('tarif', ResourceAction::Update))
                    <form method="POST" action="{{ route('admin.turnamen.tarif.store', $tournament) }}"
                          class="mt-5 grid items-end gap-3 border-t border-line pt-5 sm:grid-cols-[1fr_1fr_160px_auto]">
                        @csrf

                        <x-ui.select name="kategori" label="Kategori"
                                     :options="['' => 'Semua kategori'] + $kategori" />

                        <x-ui.select name="golongan_usia" label="Golongan usia"
                                     :options="['' => 'Semua golongan'] + $golongan" />

                        <x-ui.input type="number" name="amount" label="Nominal (Rp)" required />

                        <x-ui.button type="submit">Simpan</x-ui.button>
                    </form>
                @endresource
            @endunless
        </x-ui.card>

        <x-ui.card title="Biaya tetap kontingen">
            <p class="mb-4 text-base2 text-ink-muted">
                Ditagih sekali per kontingen, berapa pun jumlah atletnya. Kosongkan dengan nilai 0
                bila tidak dipakai.
            </p>

            @unless ($terkunci)
                @resource(rk('tarif', ResourceAction::Update))
                    <form method="POST" action="{{ route('admin.turnamen.tarif.kontingen', $tournament) }}"
                          class="grid items-end gap-3 sm:grid-cols-[1fr_160px_auto]">
                        @csrf

                        <x-ui.input name="label" label="Keterangan"
                                    :value="$tarifKontingen?->label ?? 'Biaya tetap kontingen'" />

                        <x-ui.input type="number" name="amount" label="Nominal (Rp)"
                                    :value="$tarifKontingen?->amount ?? 0" required />

                        <x-ui.button type="submit">Simpan</x-ui.button>
                    </form>
                @endresource
            @else
                <p class="silat-angka font-mono text-base2 text-ink">
                    {{ $tarifKontingen?->rupiah() ?? 'Rp 0' }}
                </p>
            @endunless
        </x-ui.card>
    </div>
</x-layouts.admin>
