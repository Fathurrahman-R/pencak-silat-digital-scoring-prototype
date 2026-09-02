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
            <x-si.badge varian="sukses">
                Terkunci oleh {{ $bracket->locker?->name ?? '—' }} · {{ $bracket->locked_at->translatedFormat('d M Y, H:i') }}
            </x-si.badge>

            @resource(rk('bagan', ResourceAction::Delete))
                <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                             x-on:click="$dispatch('modal-open', 'buka-kunci')">
                    Buka kunci
                </x-si.tombol>
            @endresource
        @else
            {{--
                Susun ulang berdiri DI SINI, bukan di daftar kelas.

                Mengacak ulang undian adalah keputusan yang diambil setelah
                melihat susunannya -- panitia menekan tombol ini karena ada
                yang salah pada bagan yang sedang dilihatnya. Di daftar kelas,
                tombol yang sama ditekan tanpa satu pun pasangan terlihat.
            --}}
            @resource(rk('bagan', ResourceAction::Create))
                <x-si.tombol tipe="button" varian="kedua" ukuran="kecil"
                             x-on:click="$dispatch('modal-open', 'susun-ulang')">
                    Susun ulang
                </x-si.tombol>
            @endresource

            @resource(rk('bagan', ResourceAction::Update))
                <x-si.tombol tipe="button" ukuran="kecil" x-on:click="$dispatch('modal-open', 'kunci-bagan')" ikon="lock">
                    Kunci bagan
                </x-si.tombol>
            @endresource
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @unless ($bracket->terkunci())
            <x-si.callout varian="perhatian" judul="Bagan ini masih draf">
                Susunannya masih bisa ditukar. Setelah dikunci, tempat yang bergeser berarti kontingen
                menyiapkan lawan yang keliru — kesalahan yang tidak bisa diperbaiki di hari-H.
            </x-si.callout>

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
        @resource(rk('bagan', ResourceAction::Create))
            <x-si.modal id="susun-ulang" judul="Susun ulang bagan {{ $weightClass->name }}?">
                <div class="space-y-3">
                    <p class="text-[11px] tracking-[.1em] text-warning uppercase">Undian akan berubah seluruhnya</p>

                    <p class="text-[14px] leading-relaxed text-ink-secondary">
                        Undian diacak ulang dari <strong class="text-ink">{{ $pesertaSah }}</strong> peserta sah
                        yang ada sekarang. Seluruh pasangan berubah, termasuk yang sudah diumumkan ke kontingen.
                    </p>

                    <div class="rounded-[var(--radius)] border-l-[3px] border-line bg-surface-inset px-3.5 py-2.5">
                        <p class="text-[13px] font-semibold text-ink">Yang tidak berubah</p>
                        <p class="mt-0.5 text-[13px] leading-relaxed text-ink-secondary">
                            Peserta yang masuk bagan tetap orang yang sama. Bagan kelas lain tidak tersentuh.
                        </p>
                    </div>

                    {{-- Jalan yang lebih kecil disebut lebih dulu: panitia yang menekan
                         "Susun ulang" sering sebenarnya hanya ingin memindahkan satu orang. --}}
                    <p class="text-[13px] leading-relaxed text-ink-secondary">
                        Hanya ingin memindahkan satu pesilat? Pakai <strong class="text-ink">Tukar tempat</strong>
                        di halaman ini — ia tidak mengubah pasangan yang lain.
                    </p>
                </div>

                <x-slot:footer>
                    <x-si.tombol varian="kedua" tipe="button"
                                 x-on:click="$dispatch('modal-close', 'susun-ulang')">Tidak jadi</x-si.tombol>

                    <form method="POST" action="{{ route('admin.turnamen.bagan.susun', [$tournament, $weightClass]) }}">
                        @csrf
                        <x-si.tombol tipe="submit">Acak ulang undian</x-si.tombol>
                    </form>
                </x-slot:footer>
            </x-si.modal>
        @endresource

        @resource(rk('bagan', ResourceAction::Update))
            <x-si.modal id="kunci-bagan" judul="Kunci bagan" ukuran="kecil">
                Setelah dikunci, susunan <strong>{{ $weightClass->name }}</strong> tidak bisa disusun ulang
                maupun ditukar lagi. Yakin melanjutkan?

                <x-slot:footer>
                    <x-si.tombol varian="kedua" tipe="button"
                                 x-on:click="$dispatch('modal-close', 'kunci-bagan')">Batal</x-si.tombol>

                    <form method="POST" action="{{ route('admin.turnamen.bagan.kunci', [$tournament, $weightClass]) }}">
                        @csrf
                        <x-si.tombol tipe="submit">Kunci</x-si.tombol>
                    </form>
                </x-slot:footer>
            </x-si.modal>
        @endresource
    @else
        @resource(rk('bagan', ResourceAction::Delete))
            <x-si.modal id="buka-kunci" judul="Buka kunci bagan" ukuran="kecil">
                <form method="POST" id="buka-kunci-form"
                      action="{{ route('admin.turnamen.bagan.buka-kunci', [$tournament, $weightClass]) }}"
                      class="space-y-4">
                    @csrf

                    <x-si.callout varian="bahaya" judul="Tindakan ini tercatat di jejak audit">
                        Kontingen mungkin sudah melihat bagan ini dan menyiapkan lawannya. Gunakan hanya
                        untuk memperbaiki kesalahan penyusunan, bukan untuk mengubah hasil undian.
                    </x-si.callout>

                    <x-si.isian-panjang name="alasan" label="Alasan" baris="3" wajib
                                        bantuan="Dibaca dari jejak audit bila kelak dipertanyakan." />
                </form>

                <x-slot:footer>
                    <x-si.tombol varian="kedua" tipe="button"
                                 x-on:click="$dispatch('modal-close', 'buka-kunci')">Batal</x-si.tombol>
                    <x-si.tombol varian="bahaya" tipe="submit" form="buka-kunci-form">Buka kunci</x-si.tombol>
                </x-slot:footer>
            </x-si.modal>
        @endresource
    @endif
</x-layouts.admin>
