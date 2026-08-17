<x-layouts.silat :title="$tournament->name">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col gap-6 p-4">
        <header>
            <p class="text-[13px] tracking-[.14em] text-silat-emas">LIVE SCORE</p>
            <h1 class="text-[22px] font-medium text-silat-teks">{{ $tournament->name }}</h1>
        </header>

        <section>
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Gelanggang</p>

            @forelse ($arenas as $arena)
                <a href="{{ route('live.gelanggang', $arena) }}"
                   class="mb-2 block rounded-silat bg-silat-panel px-4 py-3 text-[14px] text-silat-teks hover:bg-white/5">
                    {{ $arena->name }}
                </a>
            @empty
                <p class="text-[13px] text-silat-teks-redup">Belum ada gelanggang untuk kejuaraan ini.</p>
            @endforelse
        </section>

        <section>
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Kelas Tanding</p>

            <div class="divide-y divide-silat-garis rounded-silat bg-silat-panel">
                @forelse ($kelas as $baris)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14px] text-silat-teks">
                                {{ $baris['kelas']->jenis_kelamin->label() }}
                                {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                            </p>
                            @if ($baris['juara'])
                                <p class="truncate text-[12px] text-silat-emas">Juara: {{ $baris['juara'] }}</p>
                            @endif
                        </div>

                        @if ($baris['punya_bagan'])
                            <a href="{{ route('live.turnamen.bagan', [$tournament, $baris['kelas']]) }}"
                               class="shrink-0 rounded-silat bg-white/5 px-3 py-1.5 text-[12px] text-silat-teks hover:bg-white/10">
                                Lihat bagan
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-3 text-[13px] text-silat-teks-redup">Belum ada kelas tanding.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.silat>
