<x-layouts.silat :title="'Bagan — '.$weightClass->name">
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col gap-4 p-4">
        <header>
            <a href="{{ route('live.turnamen', $tournament) }}" class="text-[12px] text-silat-teks-redup hover:text-silat-teks">
                &larr; {{ $tournament->name }}
            </a>
            <h1 class="mt-1 text-[20px] font-medium text-silat-teks">
                {{ $weightClass->jenis_kelamin->label() }} {{ $weightClass->golongan_usia->label() }} — {{ $weightClass->name }}
            </h1>
        </header>

        @if (! $bracket)
            <p class="text-[13px] text-silat-teks-redup">Bagan kelas ini belum tersusun.</p>
        @else
            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach ($babak as $round => $partaiSatuBabak)
                    <div class="w-[260px] shrink-0">
                        <p class="mb-2 text-[13px] tracking-wide text-silat-teks-redup">{{ $bracket->namaBabak($round) }}</p>

                        <div class="flex flex-col gap-2">
                            @foreach ($partaiSatuBabak->sortBy('position') as $partai)
                                <div class="rounded-silat bg-silat-panel p-2.5">
                                    @foreach (['red' => $partai->red, 'blue' => $partai->blue] as $sudut => $peserta)
                                        <div @class([
                                            'rounded-[3px] px-2 py-1.5 text-[13px]',
                                            'bg-white/10 text-silat-teks' => $partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id,
                                            'text-silat-teks-redup' => ! ($partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id),
                                        ])>
                                            @if ($peserta)
                                                {{ $peserta->athletes->pluck('name')->implode(', ') }}
                                                <span class="text-[11px]">· {{ $peserta->contingent->name }}</span>
                                            @else
                                                <span class="text-[11px]">{{ $partai->bye() ? 'Bye' : '—' }}</span>
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
</x-layouts.silat>
