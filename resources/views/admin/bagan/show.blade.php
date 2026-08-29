@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Bagan {{ $weightClass->name }}"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bagan' => route('admin.turnamen.bagan.index', $tournament),
                     $weightClass->name => null,
                 ]">
    <x-slot:actions>
        @if ($bracket->terkunci())
            <x-ui.badge variant="success">
                Terkunci oleh {{ $bracket->locker?->name ?? '—' }} · {{ $bracket->locked_at->translatedFormat('d M Y, H:i') }}
            </x-ui.badge>

            @resource(rk('bagan', ResourceAction::Delete))
                <x-ui.button type="button" variant="secondary" size="sm"
                             x-on:click="$dispatch('modal-open', 'buka-kunci')">
                    Buka kunci
                </x-ui.button>
            @endresource
        @else
            @resource(rk('bagan', ResourceAction::Update))
                <x-ui.button type="button" size="sm" x-on:click="$dispatch('modal-open', 'kunci-bagan')">
                    <x-ui.icon name="lock" class="h-4 w-4" />
                    Kunci bagan
                </x-ui.button>
            @endresource
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @unless ($bracket->terkunci())
            <x-ui.alert variant="warning" title="Bagan ini masih draf">
                Susunannya masih bisa ditukar. Setelah dikunci, tempat yang bergeser berarti kontingen
                menyiapkan lawan yang keliru — kesalahan yang tidak bisa diperbaiki di hari-H.
            </x-ui.alert>

            @resource(rk('bagan', ResourceAction::Update))
                {{--
                    MEMILIH ORANG, BUKAN NOMOR POSISI.

                    Susunan lama memberi dua dropdown berisi "Posisi 1", "Posisi 2",
                    dan seterusnya. Panitia yang ingin memindahkan Bayu harus
                    menerjemahkan namanya jadi angka lebih dulu — di layar yang
                    justru menampilkan nama itu tepat di sebelahnya.

                    Sekarang yang dipilih adalah pesilatnya, dan nomor tempat
                    ikut sebagai keterangan. Akibatnya dinyatakan sebelum
                    tombolnya ditekan.
                --}}
                @php
                    $pilihan = $bracket->slots->sortBy('position')->mapWithKeys(fn ($s) => [
                        $s->position => $s->registration
                            ? $s->registration->athletes->pluck('name')->implode(', ')
                                .' — '.$s->registration->contingent->name.' (tempat '.$s->position.')'
                            : 'Tempat '.$s->position.' — kosong (bye)',
                    ]);
                @endphp

                <x-si.kartu judul="Tukar tempat"
                            keterangan="Dipakai saat dua pesilat dari kontingen yang sama bertemu di babak pertama.">
                    <form method="POST" action="{{ route('admin.turnamen.bagan.tukar', [$tournament, $weightClass]) }}"
                          class="flex flex-wrap items-end gap-3">
                        @csrf

                        <div class="w-[320px]">
                            <label for="posisi_a" class="text-[14px] font-semibold text-ink">Pesilat pertama</label>
                            <select id="posisi_a" name="posisi_a" required
                                    class="mt-1.5 h-11 w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3 text-[15px] text-ink">
                                <option value="">Pilih pesilat…</option>
                                @foreach ($pilihan as $posisi => $label)
                                    <option value="{{ $posisi }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-[320px]">
                            <label for="posisi_b" class="text-[14px] font-semibold text-ink">Tukar dengan</label>
                            <select id="posisi_b" name="posisi_b" required
                                    class="mt-1.5 h-11 w-full rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3 text-[15px] text-ink">
                                <option value="">Pilih pesilat…</option>
                                @foreach ($pilihan as $posisi => $label)
                                    <option value="{{ $posisi }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-si.tombol tipe="submit" varian="kedua">Tukar tempat keduanya</x-si.tombol>
                    </form>
                </x-si.kartu>
            @endresource
        @endunless

        <x-si.kartu>
            <x-si.pohon-bagan :pohon="$pohon" />
        </x-si.kartu>
    </div>

    @unless ($bracket->terkunci())
        @resource(rk('bagan', ResourceAction::Update))
            <x-ui.modal id="kunci-bagan" title="Kunci bagan" size="sm">
                Setelah dikunci, susunan <strong>{{ $weightClass->name }}</strong> tidak bisa disusun ulang
                maupun ditukar lagi. Yakin melanjutkan?

                <x-slot:footer>
                    <x-ui.button variant="secondary" type="button"
                                 x-on:click="$dispatch('modal-close', 'kunci-bagan')">Batal</x-ui.button>

                    <form method="POST" action="{{ route('admin.turnamen.bagan.kunci', [$tournament, $weightClass]) }}">
                        @csrf
                        <x-ui.button type="submit">Kunci</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.modal>
        @endresource
    @else
        @resource(rk('bagan', ResourceAction::Delete))
            <x-ui.modal id="buka-kunci" title="Buka kunci bagan" size="sm">
                <form method="POST" id="buka-kunci-form"
                      action="{{ route('admin.turnamen.bagan.buka-kunci', [$tournament, $weightClass]) }}"
                      class="space-y-4">
                    @csrf

                    <x-ui.alert variant="danger" title="Tindakan ini tercatat di jejak audit">
                        Kontingen mungkin sudah melihat bagan ini dan menyiapkan lawannya. Gunakan hanya
                        untuk memperbaiki kesalahan penyusunan, bukan untuk mengubah hasil undian.
                    </x-ui.alert>

                    <x-ui.textarea name="alasan" label="Alasan" rows="3" required
                                   hint="Dibaca dari jejak audit bila kelak dipertanyakan." />
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" type="button"
                                 x-on:click="$dispatch('modal-close', 'buka-kunci')">Batal</x-ui.button>
                    <x-ui.button variant="danger" type="submit" form="buka-kunci-form">Buka kunci</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endresource
    @endif
</x-layouts.admin>
