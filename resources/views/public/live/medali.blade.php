<x-layouts.silat :title="'Perolehan Medali — '.$tournament->name" permukaan="publik">
    {{--
        Emoji 🥇🥈🥉 dibuang dari halaman ini.

        Ia dirender berbeda di tiap sistem — datar di Windows, timbul di iOS,
        kadang jadi kotak kosong di peramban gelanggang — dan tidak pernah jadi
        bagian sistem desain, jadi tidak ada satu pun angka kontras yang
        berlaku untuknya. Penggantinya kolom berjudul: emas, perak, perunggu,
        jumlah. Angka rata kanan dengan numeral tabular bisa dibandingkan
        sebaris ke bawah, yang justru gunanya tabel peringkat.

        Emas hanya menandai baris peringkat pertama. Begitu ia dipakai untuk
        eyebrow atau hiasan judul, ia berhenti berarti "juara".
    --}}
    <x-silat.kepala-publik :judul="$tournament->name" keterangan="Perolehan medali"
                           :tautan="['Kejuaraan' => route('live.turnamen', $tournament), 'Medali' => route('live.turnamen.medali', $tournament)]"
                           aktif="Medali" />

    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-10 px-5 pt-8 pb-16 sm:px-10">
        <h1 class="text-[26px] font-semibold tracking-[-0.025em] text-silat-teks">Perolehan medali</h1>

        <section>
            <div class="flex items-baseline justify-between rounded-t-silat-besar border border-silat-garis bg-silat-panel px-4 py-3">
                <span class="silat-angka text-[12px] font-semibold tracking-[.1em] text-silat-teks uppercase">Peringkat umum</span>
                <div class="silat-angka flex items-baseline gap-4 text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">
                    <span class="w-12 text-right">Emas</span>
                    <span class="w-12 text-right">Perak</span>
                    <span class="w-14 text-right">Perunggu</span>
                    <span class="w-12 text-right">Jumlah</span>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-silat-besar border border-t-0 border-silat-garis">
            @forelse ($peringkatUmum as $i => $baris)
                @php($juara = $i === 0)
                <div @class(['flex items-baseline gap-4 px-4 py-3', 'border-t border-silat-garis' => $i > 0])>
                    <span class="silat-angka w-6 text-[14px] tabular-nums {{ $juara ? 'text-silat-emas' : 'text-silat-teks-redup' }}">{{ $i + 1 }}</span>
                    <span class="min-w-0 flex-1 truncate text-[17px] {{ $juara ? 'font-semibold' : '' }} text-silat-teks">{{ $baris['kontingen'] }}</span>
                    <span class="silat-angka w-12 text-right text-[17px] font-medium tabular-nums {{ $juara ? 'text-silat-emas' : 'text-silat-teks' }}">{{ $baris['emas'] }}</span>
                    <span class="silat-angka w-12 text-right text-[17px] tabular-nums text-silat-teks-redup">{{ $baris['perak'] }}</span>
                    <span class="silat-angka w-14 text-right text-[17px] tabular-nums text-silat-teks-redup">{{ $baris['perunggu'] }}</span>
                    <span class="silat-angka w-12 text-right text-[17px] font-medium tabular-nums text-silat-teks">{{ $baris['emas'] + $baris['perak'] + $baris['perunggu'] }}</span>
                </div>
            @empty
                {{-- Keadaan kosong menyebutkan APA YANG MEMBUKA ISINYA. --}}
                <div class="px-6 py-16 text-center">
                    <p class="text-[16px] font-semibold text-silat-teks">Belum ada medali</p>
                    <p class="mx-auto mt-1 max-w-[64ch] text-[14px] leading-relaxed text-silat-teks-redup">
                        Medali muncul setelah hasil partai atau penampilan Jurus disahkan Dewan Wasit Juri.
                    </p>
                </div>
            @endforelse
            </div>
        </section>

        @if ($tanding->isNotEmpty())
            <section>
                <h2 class="mb-3.5 text-[20px] font-semibold tracking-[-0.02em] text-silat-teks">Juara kelas Tanding</h2>

                @foreach ($tanding as $baris)
                    <div class="flex flex-col gap-1 border-b border-silat-garis py-3 sm:flex-row sm:items-baseline sm:gap-6">
                        <div class="w-full shrink-0 truncate text-[14px] text-silat-teks-redup sm:w-[230px]">
                            {{ $baris['kelas']->jenis_kelamin->label() }} {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline gap-3">
                                <span class="w-16 shrink-0 text-[11px] tracking-[.1em] text-silat-emas uppercase">Emas</span>
                                <span class="min-w-0 flex-1 truncate text-[16px] font-medium text-silat-teks">{{ $baris['emas']->athletes->pluck('name')->implode(', ') }}</span>
                            </div>
                            <div class="mt-1 flex items-baseline gap-3">
                                <span class="w-16 shrink-0 text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">Perak</span>
                                <span class="min-w-0 flex-1 truncate text-[15px] text-silat-teks-redup">{{ $baris['perak']->athletes->pluck('name')->implode(', ') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($jurus->isNotEmpty())
            <section>
                <div class="border-b-2 border-silat-teks pb-2 text-[11px] tracking-[.28em] text-silat-teks uppercase">
                    Juara nomor Jurus
                </div>

                @foreach ($jurus as $baris)
                    <div class="flex flex-col gap-1 border-b border-silat-garis py-3 sm:flex-row sm:items-baseline sm:gap-6">
                        <div class="w-full shrink-0 truncate text-[14px] text-silat-teks-redup sm:w-[230px]">{{ $baris['nomor']->nama() }}</div>
                        <div class="min-w-0 flex-1">
                            @if ($baris['emas'])
                                <div class="flex items-baseline gap-3">
                                    <span class="w-16 shrink-0 text-[11px] tracking-[.1em] text-silat-emas uppercase">Emas</span>
                                    <span class="min-w-0 flex-1 truncate text-[16px] font-medium text-silat-teks">{{ $baris['emas']->athletes->pluck('name')->implode(', ') }}</span>
                                </div>
                            @endif
                            @if ($baris['perak'])
                                <div class="mt-1 flex items-baseline gap-3">
                                    <span class="w-16 shrink-0 text-[11px] tracking-[.1em] text-silat-teks-redup uppercase">Perak</span>
                                    <span class="min-w-0 flex-1 truncate text-[15px] text-silat-teks-redup">{{ $baris['perak']->athletes->pluck('name')->implode(', ') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
</x-layouts.silat>
