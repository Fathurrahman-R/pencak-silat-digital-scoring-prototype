<x-layouts.silat :title="$tournament->name">
    {{--
        Halaman kejuaraan publik: PINTU MASUK, bukan etalase.

        Tiga pertanyaan yang dibawa orang ke sini, urut sesuai seringnya:
        apa yang sedang berjalan sekarang, kelas anak saya sudah sampai mana,
        dan siapa juaranya. Susunan halaman mengikuti urutan itu.

        Dialek publik: radius nol, pembatas berupa garis dan tipografi, bukan
        kotak berlatar. Lihat docs/BRIEF-DESAIN.md §3.
    --}}
    <div class="mx-auto flex min-h-screen max-w-[860px] flex-col gap-10 p-4 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('home') }}" class="text-[12px] text-silat-teks-redup">&larr; Semua kejuaraan</a>
                <h1 class="mt-1 text-[24px] leading-tight font-medium text-silat-teks">{{ $tournament->name }}</h1>
                <p class="silat-angka mt-1 text-[13px] text-silat-teks-redup">
                    {{ $tournament->venue ? $tournament->venue.' · ' : '' }}{{ $tournament->starts_on?->translatedFormat('d M Y') }}
                </p>
            </div>
            <a href="{{ route('live.turnamen.medali', $tournament) }}"
               class="shrink-0 border-b border-silat-tepi-kendali pb-1 text-[14px] font-medium text-silat-teks">
                Perolehan medali
            </a>
        </header>

        <section>
            <div class="border-b-2 border-silat-teks pb-2 text-[11px] tracking-[.28em] text-silat-teks uppercase">
                Gelanggang
            </div>

            @forelse ($arenas as $papan)
                @php($partai = $papan['partai'])
                <a href="{{ route('live.gelanggang', $papan['arena']) }}"
                   class="flex flex-col gap-3 border-b border-silat-garis py-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-[16px] font-medium text-silat-teks">{{ $papan['arena']->name }}</span>
                        <span class="silat-angka text-[12px] text-silat-teks-redup">
                            @if ($partai)
                                PARTAI {{ $partai->id }} · {{ strtoupper($partai->bracket->weightClass->name) }}
                            @else
                                TIDAK ADA PARTAI BERJALAN
                            @endif
                        </span>
                    </div>

                    @if ($partai)
                        {{-- Sudut ditandai batang tepi. Bidang penuh dipakai papan skor
                             gelanggang; di daftar begini ia menutupi halaman. --}}
                        @foreach ([['red', 'merah', 'bg-silat-merah'], ['blue', 'biru', 'bg-silat-biru']] as [$sisi, $nama, $warna])
                            <div class="flex items-stretch gap-4">
                                <div class="w-[4px] shrink-0 {{ $warna }}"></div>
                                <div class="min-w-0 flex-1 truncate text-[17px] text-silat-teks">
                                    {{ $partai->{$sisi}?->athletes->pluck('name')->implode(', ') ?? '—' }}
                                </div>
                                <div class="silat-angka text-[34px] leading-none font-medium text-silat-teks tabular-nums">
                                    {{ $papan['skor'][$nama] }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </a>
            @empty
                <div class="border border-dashed border-silat-tepi-kendali px-5 py-8">
                    <p class="text-[16px] font-medium text-silat-teks">Belum ada gelanggang</p>
                    <p class="mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                        Gelanggang muncul di sini setelah panitia menetapkannya untuk kejuaraan ini.
                    </p>
                </div>
            @endforelse
        </section>

        <section>
            <div class="flex items-baseline justify-between border-b-2 border-silat-teks pb-2">
                <span class="text-[11px] tracking-[.28em] text-silat-teks uppercase">Kelas Tanding</span>
                <span class="silat-angka text-[12px] text-silat-teks-redup tabular-nums">
                    {{ $kelas->flatten(1)->count() }} kelas
                </span>
            </div>

            @forelse ($kelas as $golongan => $barisan)
                {{-- Judul golongan usia lengket saat digulir: satu kejuaraan bisa
                     punya seratus lebih kelas, dan tanpa ini orang kehilangan
                     tahu sedang berada di golongan mana. --}}
                <div class="sticky top-0 z-10 bg-silat-latar pt-5 pb-1 text-[13px] font-semibold text-silat-teks">
                    {{ $golongan }}
                </div>

                @foreach ($barisan as $baris)
                    <div class="flex items-baseline justify-between gap-4 border-b border-silat-garis py-3">
                        <div class="min-w-0">
                            <div class="truncate text-[15px] text-silat-teks">
                                {{ $baris['kelas']->jenis_kelamin->label() }} — {{ $baris['kelas']->name }}
                            </div>
                            @if ($baris['juara'])
                                <div class="mt-0.5 flex items-baseline gap-2">
                                    <span class="shrink-0 text-[11px] tracking-[.1em] text-silat-emas uppercase">Juara</span>
                                    <span class="truncate text-[13px] text-silat-teks">{{ $baris['juara'] }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($baris['punya_bagan'])
                            <a href="{{ route('live.turnamen.bagan', [$tournament, $baris['kelas']]) }}"
                               class="shrink-0 border-b border-silat-tepi-kendali text-[13px] text-silat-teks">
                                Lihat bagan
                            </a>
                        @else
                            <span class="silat-angka shrink-0 text-[12px] text-silat-teks-redup">Bagan belum tersusun</span>
                        @endif
                    </div>
                @endforeach
            @empty
                <div class="border border-dashed border-silat-tepi-kendali px-5 py-8">
                    <p class="text-[16px] font-medium text-silat-teks">Belum ada kelas Tanding</p>
                    <p class="mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                        Kelas terbit setelah panitia membuka pendaftaran kejuaraan ini.
                    </p>
                </div>
            @endforelse
        </section>

        @if ($jurusEvents->isNotEmpty())
            <section>
                <div class="border-b-2 border-silat-teks pb-2 text-[11px] tracking-[.28em] text-silat-teks uppercase">
                    Nomor Jurus
                </div>

                @foreach ($jurusEvents as $baris)
                    <div class="pt-5 pb-1 text-[13px] font-semibold text-silat-teks">{{ $baris['nomor']->nama() }}</div>

                    @foreach ($baris['peringkat'] as $i => $urutan)
                        @php($penampilan = $urutan['penampilan'])
                        <div class="flex items-baseline gap-4 border-b border-silat-garis py-3">
                            <span class="silat-angka w-5 shrink-0 text-[13px] tabular-nums {{ $i === 0 ? 'text-silat-emas' : 'text-silat-teks-redup' }}">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] text-silat-teks">
                                    {{ $penampilan->registration->athletes->pluck('name')->implode(', ') }}
                                </div>
                                <div class="truncate text-[12px] text-silat-teks-redup">
                                    {{ $penampilan->registration->contingent->name }}
                                </div>
                            </div>
                            {{--
                                Diskualifikasi ditulis penuh, bukan "DQ". Halaman ini
                                dibaca penonton tribun dan keluarga pesilat, dan
                                singkatan itu cuma dikenal orang dalam.
                            --}}
                            <span class="silat-angka shrink-0 text-right text-[18px] font-medium tabular-nums {{ $urutan['skor'] === null ? 'text-silat-teks-redup' : 'text-silat-teks' }}">
                                {{ $urutan['skor'] === null ? 'Diskualifikasi' : number_format($urutan['skor'], 2) }}
                            </span>
                        </div>
                    @endforeach
                @endforeach
            </section>
        @endif
    </div>
</x-layouts.silat>
