<x-layouts.silat :title="'Rekap Medali — '.$tournament->name">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col gap-6 p-4">
        <header>
            <a href="{{ route('live.turnamen', $tournament) }}" class="text-[12px] text-silat-teks-redup hover:text-silat-teks">
                &larr; {{ $tournament->name }}
            </a>
            <p class="mt-1 text-[13px] tracking-[.14em] text-silat-emas">REKAP MEDALI</p>
        </header>

        <section>
            <p class="mb-2 text-[13px] font-medium text-silat-teks">Peringkat Umum</p>

            <div class="divide-y divide-silat-garis rounded-silat bg-silat-panel">
                @forelse ($peringkatUmum as $i => $baris)
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <span class="silat-angka w-6 text-center text-[13px] text-silat-teks-redup">{{ $i + 1 }}</span>
                        <span class="flex-1 truncate text-[14px] text-silat-teks">{{ $baris['kontingen'] }}</span>
                        <span class="text-[12px] text-silat-teks-redup">🥇 {{ $baris['emas'] }}</span>
                        <span class="text-[12px] text-silat-teks-redup">🥈 {{ $baris['perak'] }}</span>
                        <span class="text-[12px] text-silat-teks-redup">🥉 {{ $baris['perunggu'] }}</span>
                    </div>
                @empty
                    <p class="px-4 py-3 text-[13px] text-silat-teks-redup">Belum ada medali yang disahkan.</p>
                @endforelse
            </div>
        </section>

        @if ($tanding->isNotEmpty())
            <section>
                <p class="mb-2 text-[13px] font-medium text-silat-teks">Juara Kelas Tanding</p>
                <div class="divide-y divide-silat-garis rounded-silat bg-silat-panel">
                    @foreach ($tanding as $baris)
                        <div class="px-4 py-2.5">
                            <p class="text-[13px] text-silat-teks">
                                {{ $baris['kelas']->jenis_kelamin->label() }} {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                            </p>
                            <p class="text-[12px] text-silat-teks-redup">
                                🥇 {{ $baris['emas']->athletes->pluck('name')->implode(', ') }}
                                · 🥈 {{ $baris['perak']->athletes->pluck('name')->implode(', ') }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($jurus->isNotEmpty())
            <section>
                <p class="mb-2 text-[13px] font-medium text-silat-teks">Juara Nomor Jurus</p>
                <div class="divide-y divide-silat-garis rounded-silat bg-silat-panel">
                    @foreach ($jurus as $baris)
                        <div class="px-4 py-2.5">
                            <p class="text-[13px] text-silat-teks">{{ $baris['nomor']->nama() }}</p>
                            <p class="text-[12px] text-silat-teks-redup">
                                @if ($baris['emas']) 🥇 {{ $baris['emas']->athletes->pluck('name')->implode(', ') }} @endif
                                @if ($baris['perak']) · 🥈 {{ $baris['perak']->athletes->pluck('name')->implode(', ') }} @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.silat>
