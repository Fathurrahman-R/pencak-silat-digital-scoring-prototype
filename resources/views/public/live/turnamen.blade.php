<x-layouts.silat :title="$tournament->name" permukaan="publik" :realtime="false">
    {{--
        Halaman kejuaraan publik: PINTU MASUK, bukan etalase.

        Tiga pertanyaan yang dibawa orang ke sini, urut sesuai seringnya:
        apa yang sedang berjalan sekarang, kelas anak saya sudah sampai mana,
        dan siapa juaranya. Susunan halaman mengikuti urutan itu.

        Wajah publik: suasana terang bawaan, kartu bertepi, radius yang sama
        dengan admin. Lihat BRIEF-DIGITAL-SCORING.md §9.
    --}}
    <x-silat.kepala-publik :judul="$tournament->name"
                           :keterangan="trim(($tournament->venue ? $tournament->venue.' · ' : '').($tournament->starts_on?->translatedFormat('d M Y') ?? ''), ' ·')"
                           :tautan="['Semua kejuaraan' => route('home'), 'Medali' => route('live.turnamen.medali', $tournament)]" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-10 px-5 pt-8 pb-16 sm:px-10">
        <section>
            <div class="mb-4 flex items-baseline gap-3">
                <h1 class="text-[26px] font-semibold tracking-[-0.025em] text-silat-teks">Sedang berlangsung</h1>
                <span class="silat-angka flex items-center gap-2 text-[12px] text-silat-teks-redup">
                    <span class="size-[7px] rounded-full bg-silat-hidup"></span>diperbarui otomatis
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
            @forelse ($arenas as $papan)
                @php($partai = $papan['partai'])
                <a href="{{ route('live.gelanggang', $papan['arena']) }}"
                   class="block overflow-hidden rounded-silat-besar border border-silat-garis no-underline">
                    <div class="flex items-center gap-2.5 border-b border-silat-garis bg-silat-panel px-4.5 py-3.5">
                        <span class="silat-angka text-[12px] font-semibold tracking-[.1em] text-silat-teks uppercase">{{ $papan['arena']->name }}</span>
                        <span @class([
                            'silat-angka inline-flex h-[22px] items-center rounded-silat-kecil px-2.5 text-[10px] font-semibold tracking-[.08em] uppercase',
                            'bg-silat-aksi text-silat-aksi-teks' => (bool) $partai,
                            'border border-silat-tepi-kendali text-silat-teks-kedua' => ! $partai,
                        ])>{{ $partai ? 'Berjalan' : 'Tidak ada partai' }}</span>
                        @if ($partai)
                            <span class="ml-auto truncate text-[13px] text-silat-teks-redup">{{ $partai->bracket->weightClass->namaLengkap() }}</span>
                        @endif
                    </div>

                    @if ($partai)
                        {{-- Sudut ditandai batang tepi. Bidang penuh dipakai papan skor
                             gelanggang; di daftar begini ia menutupi halaman. --}}
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 px-4.5 py-5.5">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="h-11 w-1 shrink-0 rounded-full bg-silat-merah"></span>
                                <div class="min-w-0">
                                    <p class="truncate text-[16px] font-semibold tracking-[-0.01em] text-silat-teks">
                                        {{ $partai->red?->athletes->pluck('name')->implode(', ') ?? '—' }}
                                    </p>
                                    <p class="mt-0.5 truncate text-[13px] text-silat-teks-redup">
                                        {{ $partai->red?->contingent->name ?? '—' }}
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0 text-center">
                                <p class="silat-angka text-[38px] leading-none font-medium text-silat-teks">
                                    {{ $papan['skor']['merah'] }} – {{ $papan['skor']['biru'] }}
                                </p>
                                <p class="silat-angka mt-1.5 text-[11px] text-silat-teks-redup">PARTAI {{ $partai->id }}</p>
                            </div>

                            <div class="flex min-w-0 items-center justify-end gap-3 text-right">
                                <div class="min-w-0">
                                    <p class="truncate text-[16px] font-semibold tracking-[-0.01em] text-silat-teks">
                                        {{ $partai->blue?->athletes->pluck('name')->implode(', ') ?? '—' }}
                                    </p>
                                    <p class="mt-0.5 truncate text-[13px] text-silat-teks-redup">
                                        {{ $partai->blue?->contingent->name ?? '—' }}
                                    </p>
                                </div>
                                <span class="h-11 w-1 shrink-0 rounded-full bg-silat-biru"></span>
                            </div>
                        </div>
                    @endif
                </a>
            @empty
                <div class="rounded-silat-besar border border-dashed border-silat-tepi-kendali px-6 py-16 text-center md:col-span-2">
                    <p class="text-[16px] font-semibold text-silat-teks">Belum ada gelanggang</p>
                    <p class="mx-auto mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                        Gelanggang muncul di sini setelah panitia menetapkannya untuk kejuaraan ini.
                    </p>
                </div>
            @endforelse
            </div>
        </section>

        <section>
            <div class="mb-3.5 flex items-baseline gap-3">
                <h2 class="text-[20px] font-semibold tracking-[-0.02em] text-silat-teks">Kelas Tanding</h2>
                <span class="silat-angka text-[11.5px] text-silat-teks-redup">{{ $kelas->flatten(1)->count() }} kelas</span>
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
                <div class="rounded-silat-besar border border-dashed border-silat-tepi-kendali px-6 py-16 text-center">
                    <p class="text-[16px] font-semibold text-silat-teks">Belum ada kelas Tanding</p>
                    <p class="mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                        Kelas terbit setelah panitia membuka pendaftaran kejuaraan ini.
                    </p>
                </div>
            @endforelse
        </section>

        @if ($jurusEvents->isNotEmpty())
            <section>
                <h2 class="mb-3.5 text-[20px] font-semibold tracking-[-0.02em] text-silat-teks">Nomor Jurus</h2>

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
