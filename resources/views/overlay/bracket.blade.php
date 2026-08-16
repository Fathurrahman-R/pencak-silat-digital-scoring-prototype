{{--
    Bagan antar partai. Statis, tanpa Alpine/Echo -- susunan bagan tidak
    berubah selama satu partai berlangsung, jadi tidak ada yang perlu
    disegarkan realtime di sini. Kelasnya dipilih lewat ?kelas=ID (lihat
    OverlayController::bracket), karena satu turnamen bisa punya ratusan
    kelas dan overlay tidak bisa menebak mana yang mau ditayangkan.

    Rekap medali penuh menyusul Fase 8; belum ada mesin hitungnya.
--}}

<x-layouts.overlay title="Bagan">
    <div class="relative h-full w-full p-[64px]">
        @if (! $bracket)
            <p class="text-[18px] text-silat-teks-redup">
                Tambahkan <span class="silat-angka text-silat-teks">?kelas=ID</span> ke alamat sumber ini untuk memilih kelas tanding yang ditayangkan.
            </p>
        @else
            <div class="mb-6">
                <p class="text-[13px] tracking-[.1em] text-silat-teks-redup">BAGAN</p>
                <h1 class="text-[28px] font-medium text-silat-teks">
                    {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
                </h1>
            </div>

            <div class="flex gap-6 overflow-x-auto">
                @foreach ($babak as $round => $partaiSatuBabak)
                    <div class="w-[300px] shrink-0">
                        <p class="mb-2 text-[14px] tracking-wide text-silat-teks-redup">{{ $bracket->namaBabak($round) }}</p>

                        <div class="flex flex-col gap-3">
                            @foreach ($partaiSatuBabak->sortBy('position') as $partai)
                                <div class="rounded-silat bg-silat-panel p-3">
                                    @foreach (['red' => $partai->red, 'blue' => $partai->blue] as $sudut => $peserta)
                                        <div @class([
                                            'rounded-[3px] px-2 py-1.5 text-[15px]',
                                            'bg-white/10 text-silat-teks' => $partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id,
                                            'text-silat-teks-redup' => ! ($partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id),
                                        ])>
                                            @if ($peserta)
                                                {{ $peserta->athletes->pluck('name')->implode(', ') }}
                                                <span class="text-[12px]">· {{ $peserta->contingent->name }}</span>
                                            @else
                                                <span class="text-[12px]">{{ $partai->bye() ? 'Bye' : '—' }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.overlay>
