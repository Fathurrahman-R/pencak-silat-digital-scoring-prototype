@props(['bracket', 'babak'])

{{--
    Pohon bagan gugur dengan garis penghubung antar babak. Dipakai overlay
    siaran (Fase 6) dan live score publik (Fase 5) -- keduanya butuh bentuk
    yang sama persis, jadi ditaruh satu tempat di sini.

    Garis penghubung memakai teknik CSS murni yang lazim dipakai bagan
    gugur: tiap partai dalam satu pasangan digambar dengan border membentuk
    sudut "L" yang bertemu di tengah, lalu diteruskan garis mendatar ke
    partai babak berikutnya. Ini butuh tinggi partai yang SAMA dalam satu
    pasangan supaya garisnya bertemu tepat di tengah -- makanya tiap kotak
    partai punya tinggi baris tetap (h-[26px] per sudut), bukan tinggi
    mengikuti isi.

    "Terakhir" dihitung sekali per babak sebagai variabel PHP biasa, bukan
    lewat $loop->parent berlapis -- pada kedalaman tiga @foreach (babak →
    pasangan → partai dalam pasangan), $loop->parent dari foreach terdalam
    menunjuk ke foreach PASANGAN, bukan foreach BABAK, dan salah pakai itu
    pernah membuat garis penghubung salah gambar di babak yang salah.

    Kotak peserta diwarnai tipis sesuai sudut (merah/biru) supaya gampang
    dikenali dari kejauhan, terpisah dari sorot pemenang yang membuatnya
    lebih terang.
--}}

<div class="flex items-stretch gap-8 overflow-x-auto pb-4">
    @php $jumlahBabak = $babak->count(); @endphp

    @foreach ($babak as $round => $partaiSatuBabak)
        @php
            $matches = $partaiSatuBabak->sortBy('position')->values();
            $babakTerakhir = $loop->iteration === $jumlahBabak;
        @endphp

        <div class="flex w-[260px] shrink-0 flex-col">
            <p class="mb-3 text-[13px] tracking-wide text-silat-teks-redup">{{ $bracket->namaBabak($round) }}</p>

            <div class="flex flex-1 flex-col justify-around gap-4">
                @foreach ($matches->chunk(2) as $pasangan)
                    <div class="relative flex flex-col justify-around gap-3">
                        @foreach ($pasangan as $idx => $partai)
                            <div @class(['relative rounded-silat bg-silat-panel p-2', 'pr-6' => ! $babakTerakhir])>
                                {{-- Siku garis: partai pertama pasangan turun, kedua naik, bertemu di tengah --}}
                                @if (! $babakTerakhir && $pasangan->count() === 2)
                                    <span @class([
                                        'pointer-events-none absolute right-0 h-1/2 w-4 border-r border-silat-garis',
                                        'top-1/2 border-b' => $idx === 0,
                                        'bottom-1/2 border-t' => $idx === 1,
                                    ])></span>
                                @endif

                                @foreach (['red' => $partai->red, 'blue' => $partai->blue] as $sudut => $peserta)
                                    @php $menang = $partai->winner_registration_id && $partai->winner_registration_id === $peserta?->id; @endphp
                                    <div @class([
                                        'flex h-[26px] items-center gap-1.5 rounded-[3px] border-l-4 px-2 text-[13px]',
                                        'border-silat-merah bg-silat-merah/25 text-silat-teks' => $sudut === 'red' && $menang,
                                        'border-silat-merah/60 bg-silat-merah/10 text-silat-teks-redup' => $sudut === 'red' && ! $menang,
                                        'border-silat-biru bg-silat-biru/25 text-silat-teks' => $sudut === 'blue' && $menang,
                                        'border-silat-biru/60 bg-silat-biru/10 text-silat-teks-redup' => $sudut === 'blue' && ! $menang,
                                    ])>
                                        @if ($peserta)
                                            <span class="truncate">{{ $peserta->athletes->pluck('name')->implode(', ') }}</span>
                                            <span class="shrink-0 truncate text-[11px] opacity-70">· {{ $peserta->contingent->name }}</span>
                                        @else
                                            <span class="text-[11px] opacity-70">{{ $partai->bye() ? 'Bye' : '—' }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        {{-- Garis mendatar dari titik tengah pasangan (atau satu-satunya partai bila ganjil) ke babak berikutnya --}}
                        @if (! $babakTerakhir)
                            <span class="pointer-events-none absolute top-1/2 -right-4 h-px w-4 -translate-y-1/2 bg-silat-garis"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
