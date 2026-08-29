@php use App\Enums\ResourceAction; @endphp

<x-layouts.admin heading="Bagan"
                 :description="$tournament->name"
                 :breadcrumb="[
                     'Kejuaraan' => route('admin.turnamen.index'),
                     $tournament->name => route('admin.turnamen.edit', $tournament),
                     'Bagan' => null,
                 ]">
    <div class="space-y-4">
        <x-si.callout varian="keterangan" judul="Bagan disusun dari peserta yang sudah disahkan">
            Peserta yang berkasnya belum lengkap atau tagihan kontingennya belum lunas tidak ikut
            masuk hitungan, meski sudah mendaftar. Susun bagan setelah verifikasi dan timbang badan
            selesai untuk kelas yang bersangkutan.
        </x-si.callout>

        <x-si.kartu judul="Kelas tanding">
            <x-slot:aksi>
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    @if ($tampil !== 'terpakai')
                        <input type="hidden" name="tampil" value="{{ $tampil }}">
                    @endif

                    <x-si.isian name="q" :value="$cari" placeholder="Cari kelas…" class="w-[200px]" />
                </form>
            </x-slot:aksi>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <x-si.saring param="tampil" semua="Semua kelas" :sekarang="$tampil === 'semua' ? null : $tampil" :pilihan="[
                             'terpakai' => 'Ada peserta atau bagan',
                             'tersusun' => 'Sudah disusun',
                             ]" />

                <p class="text-xs text-ink-muted">
                    Menampilkan {{ $kelas->count() }} dari {{ $jumlahSemua }} kelas.
                    @if ($tampil === 'terpakai' && $jumlahSemua > $jumlahTerpakai)
                        {{ $jumlahSemua - $jumlahTerpakai }} kelas tanpa peserta disembunyikan —
                        pilih <span class="text-ink">Semua kelas</span> untuk melihatnya.
                    @endif
                </p>
            </div>

            @forelse ($kelas as $k)
                @php
                    $bracket = $k->bracket;
                    $bisaSusun = $k->peserta_sah >= 2;
                @endphp

                <div class="flex flex-wrap items-center gap-4 border-b border-line py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="min-w-[220px] flex-1">
                        <p class="font-medium text-ink">
                            {{ $k->jenis_kelamin->label() }} {{ $k->golongan_usia->label() }} — {{ $k->name }}
                        </p>
                        <p class="text-xs text-ink-muted">{{ $k->rentang() }}</p>
                    </div>

                    <span class="text-sm text-ink-muted">{{ $k->peserta_sah }} peserta sah</span>

                    @if ($bracket && $bracket->terkunci())
                        <x-si.badge varian="sukses">Terkunci · {{ $bracket->size }} tempat</x-si.badge>
                    @elseif ($bracket)
                        <x-si.badge varian="perhatian">Draf · {{ $bracket->size }} tempat</x-si.badge>
                    @else
                        <x-si.badge varian="netral">Belum disusun</x-si.badge>
                    @endif

                    <div class="flex gap-1">
                        @if ($bracket)
                            <x-si.tombol :tautan="route('admin.turnamen.bagan.show', [$tournament, $k])" varian="kedua" ukuran="kecil">
                                Lihat
                            </x-si.tombol>
                        @endif

                        @resource(rk('bagan', ResourceAction::Create))
                            @if (! $bracket?->terkunci())
                                @if ($bracket)
                                    {{--
                                        Menyusun ulang MENGACAK UNDIAN DARI NOL:
                                        seluruh pasangan berubah, termasuk yang
                                        sudah diumumkan ke official kontingen yang
                                        menyiapkan atletnya berdasarkan lawan yang
                                        mereka lihat.

                                        Sebelumnya dikonfirmasi dengan confirm()
                                        bawaan peramban — kotak abu-abu tanpa rupa,
                                        dengan tombol "OK" dan "Cancel" yang
                                        bahasanya mengikuti bahasa peramban, bukan
                                        bahasa aplikasi.
                                    --}}
                                    {{--
                                        Muatan dibawa lewat data-*, bukan @js() di
                                        dalam x-on:click. JSON yang dihasilkan @js
                                        memuat tanda kutip, dan tanda kutip di dalam
                                        atribut memutus pembacaan ekspresinya —
                                        Alpine melapor "Invalid or unexpected token"
                                        dan tombolnya diam.
                                    --}}
                                    <x-si.tombol tipe="button" ukuran="kecil" :nonaktif="! $bisaSusun"
                                                 data-aksi="{{ route('admin.turnamen.bagan.susun', [$tournament, $k]) }}"
                                                 data-bagan="{{ route('admin.turnamen.bagan.show', [$tournament, $k]) }}"
                                                 data-kelas="{{ $k->jenis_kelamin->label().' '.$k->golongan_usia->label().' — '.$k->name }}"
                                                 data-peserta="{{ $k->peserta_sah }}"
                                                 x-on:click="$dispatch('susun-ulang', $el.dataset)">
                                        Susun ulang
                                    </x-si.tombol>
                                @else
                                    <form method="POST" action="{{ route('admin.turnamen.bagan.susun', [$tournament, $k]) }}">
                                        @csrf
                                        <x-si.tombol tipe="submit" ukuran="kecil" :nonaktif="! $bisaSusun">
                                            Susun bagan
                                        </x-si.tombol>
                                    </form>
                                @endif
                            @endif
                        @endresource
                    </div>
                </div>
            @empty
                @if ($cari !== '' || $tampil !== 'semua')
                    <x-si.kosong judul="Tidak ada kelas yang cocok"
                                 syarat="Belum ada kelas dengan peserta sah atau bagan. Ubah penyaring di atas untuk melihat seluruh kelas yang diturunkan dari naskah." />
                @else
                    <x-si.kosong judul="Belum ada kelas tanding"
                                 syarat="Kelas tanding diturunkan dari naskah peraturan saat kejuaraan dibuat." />
                @endif
            @endforelse
        </x-si.kartu>
    </div>

    {{--
        SATU dialog untuk seluruh halaman, bukan satu per baris. Kejuaraan
        daerah punya seratus lebih kelas, dan dialog per baris berarti seratus
        dialog tersembunyi di halaman yang sama.
    --}}
    @resource(rk('bagan', ResourceAction::Create))
        <div x-data="{ terbuka: false, aksi: '', bagan: '', kelas: '', peserta: 0 }"
             x-on:susun-ulang.window="aksi = $event.detail.aksi; bagan = $event.detail.bagan;
                                      kelas = $event.detail.kelas; peserta = $event.detail.peserta;
                                      terbuka = true"
             x-on:keydown.escape.window="terbuka = false">
            <div x-show="terbuka" x-cloak
                 class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="terbuka = false">
                <div class="w-full max-w-[480px] rounded-[var(--radius)] border border-line bg-surface-raised p-5">
                    <p class="text-[11px] tracking-[.1em] text-warning uppercase">Undian akan berubah seluruhnya</p>
                    <p class="mt-1 text-[20px] leading-tight font-semibold text-ink">
                        Susun ulang bagan <span x-text="kelas"></span>?
                    </p>

                    <p class="mt-2 text-[14px] leading-relaxed text-ink-secondary">
                        Undian diacak ulang dari <span class="font-semibold text-ink" x-text="peserta"></span>
                        peserta sah yang ada sekarang. Seluruh pasangan berubah, termasuk yang sudah
                        diumumkan ke kontingen.
                    </p>

                    <div class="mt-3 rounded-[var(--radius)] border-l-[3px] border-line bg-surface-inset px-3.5 py-2.5">
                        <p class="text-[13px] font-semibold text-ink">Yang tidak berubah</p>
                        <p class="mt-0.5 text-[13px] leading-relaxed text-ink-secondary">
                            Peserta yang masuk bagan tetap orang yang sama. Bagan kelas lain tidak tersentuh.
                        </p>
                    </div>

                    {{-- Jalan yang lebih kecil ditawarkan lebih dulu: panitia yang
                         menekan "Susun ulang" sering sebenarnya hanya ingin
                         memindahkan satu orang. --}}
                    <div class="mt-3 flex items-center justify-between gap-3 rounded-[var(--radius)] border border-line px-3.5 py-3">
                        <p class="flex-1 text-[13px] leading-relaxed text-ink-secondary">
                            Hanya ingin memindahkan satu pesilat? Tukar tempat tidak mengubah pasangan lain.
                        </p>
                        <a x-bind:href="bagan"
                           class="inline-flex h-9 shrink-0 items-center rounded-[var(--radius)] border border-line-strong px-3 text-[13px] font-semibold text-ink">
                            Buka bagan
                        </a>
                    </div>

                    <form method="POST" x-bind:action="aksi" class="mt-4 flex items-center gap-2">
                        @csrf
                        <x-si.tombol tipe="button" varian="kedua" x-on:click="terbuka = false">Tidak jadi</x-si.tombol>
                        <x-si.tombol tipe="submit">Acak ulang undian</x-si.tombol>
                    </form>
                </div>
            </div>
        </div>
    @endresource
</x-layouts.admin>
