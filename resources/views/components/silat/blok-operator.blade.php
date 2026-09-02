@props([
    'sudut' => 'red',
    'kunciSkor' => 'merah',
])

@php
    /*
     * Blok sudut untuk panel operator — mengikuti `operator-b-eksperimen.dc.html`.
     *
     * Susunannya MERAH ATAS, BIRU BAWAH, bukan kiri-kanan. Dengan dua blok
     * bertumpuk, skor, hukuman, dan indikator juri kedua sudut berdiri sekolom
     * dan bisa dibandingkan langsung — pertanyaan "yang mana milik siapa"
     * hilang sama sekali. Panel juri, wasit, live publik, dan overlay tetap
     * kiri-kanan, karena di sana kolomnya memang memetakan posisi pesilat di
     * matras.
     *
     * Bidang penuh sudut (#7a1418 / #0c2a63) dengan batang tepi warna sudut
     * terang di sisi kiri. Petak hukuman dan titik juri yang belum menyala
     * digambar sebagai TEPI, tidak pernah sebagai bidang abu.
     */
    $biru = $sudut === 'blue';

    $bidang = $biru ? 'bg-silat-biru-dalam' : 'bg-silat-merah-dalam';
    $rail = $biru ? 'bg-silat-biru' : 'bg-silat-merah';
    $tepi = $biru ? 'border-silat-teks-biru-samar' : 'border-silat-teks-merah-samar';
    $redup = $biru ? 'text-silat-teks-biru-samar' : 'text-silat-teks-merah-samar';
    $kedua = $biru ? 'text-silat-teks-biru' : 'text-silat-teks-merah-redup';

    $teknik = ['pukulan' => 'Pukulan', 'tendangan' => 'Tendangan', 'jatuhan' => 'Jatuhan'];
@endphp

<div class="{{ $bidang }} flex min-h-0 flex-1 items-stretch">
    <div class="{{ $rail }} w-2 shrink-0"></div>

    {{-- Ukuran huruf dan padding memakai `clamp()`: nilai TERBESARNYA sama
         persis dengan rancangan (nama 64px, kontingen 24px, skor 156px), jadi
         di layar gelanggang 1920px tidak ada yang berubah. Yang berubah cuma
         nasibnya di layar sempit -- HP dipegang miring, tablet 1024 -- tempat
         ukuran tetap membuat nama pesilat menabrak angka skor sampai keduanya
         tidak terbaca. --}}
    <div class="flex min-w-0 flex-1 items-center gap-[clamp(16px,2.4vw,36px)] px-[clamp(18px,3.2vw,50px)]">
        <div class="flex h-full min-w-0 flex-1 flex-col justify-evenly gap-5">
            <div class="min-w-0">
                <p class="text-[clamp(26px,4.2vw,64px)] leading-[1.1] font-semibold tracking-[-0.025em] break-words text-silat-teks"
                   x-text="(match.{{ $sudut }}?.athletes ?? []).join(', ') || '—'"></p>
                <p class="mt-1.5 truncate text-[clamp(13px,1.6vw,24px)] {{ $kedua }}" x-text="match.{{ $sudut }}?.contingent ?? '—'"></p>
            </div>

            <div class="flex flex-col items-start gap-10">
                <div>
                    <p class="silat-angka mb-2 text-[10px] tracking-[.12em] {{ $redup }} uppercase">Hukuman</p>

                    @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
                        @php($jatah = config("scoring.tanding.hukuman.{$jenis}.jumlah_kolom", 0))
                        <div class="mt-1.5 flex items-center gap-2.5">
                            <span class="w-[82px] shrink-0 text-[12.5px] {{ $kedua }}">{{ ucfirst($jenis) }}</span>
                            <div class="flex gap-1.5" aria-hidden="true">
                                @for ($i = 1; $i <= $jatah; $i++)
                                    <span class="grid h-6 w-[30px] place-items-center rounded-silat-kecil border-[1.5px] {{ $tepi }}"
                                          @class(['border-dashed' => $jenis === 'peringatan' && $i === $jatah])
                                          x-bind:class="(hukuman?.{{ $kunciSkor }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                                              ? 'bg-silat-teks border-silat-teks text-silat-panel'
                                              : '{{ $redup }}'">
                                        <x-silat.ikon :nama="$jenis" :ukuran="12" :label="null" />
                                    </span>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Indikator juri per teknik: yang terlihat operator adalah
                     berapa juri yang menekan teknik yang sama, karena itulah
                     yang menentukan sebuah nilai sah atau tidak. --}}
                <div class="flex items-center gap-4" x-show="peraturan.jumlah_juri">
                    @foreach ($teknik as $kunci => $label)
                        <div class="flex items-center gap-2">
                            <span class="silat-angka text-[10px] tracking-[.1em] {{ $redup }} uppercase">{{ $label }}</span>
                            <div class="flex gap-1.5" aria-hidden="true">
                                <template x-for="i in peraturan.jumlah_juri" :key="i">
                                    <span class="size-[15px] rounded-full border-[1.5px] {{ $tepi }}"
                                          x-bind:class="(indikatorTeknik?.{{ $sudut }}?.{{ $kunci }} ?? []).includes(i)
                                              ? 'bg-silat-teks border-silat-teks'
                                              : ''"></span>
                                </template>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex w-[40%] shrink-0 flex-row items-start justify-center gap-[clamp(8px,1.2vw,24px)]">
            <p class="silat-angka shrink-0 text-center text-[clamp(56px,10.4vw,156px)] leading-[0.8] font-medium tracking-[-0.04em] text-silat-teks"
               x-text="skorTotal.{{ $kunciSkor }}"></p>
            <span class="text-[clamp(13px,1.5vw,22px)] {{ $kedua }}" x-text="selisih.{{ $kunciSkor }}"></span>
        </div>
    </div>
</div>
