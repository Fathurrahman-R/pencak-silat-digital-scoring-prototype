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

/*
* Dua teknik saja. Jatuhan tidak lagi ditekan juri -- nilainya mutlak dan
* diterbitkan wasit -- jadi barisnya tidak akan pernah menyala. Indikator
* yang selamanya kosong terbaca operator sebagai juri yang tidak menekan,
* bukan sebagai teknik yang memang bukan urusan juri.
*/
$teknik = ['pukulan' => 'Pukulan', 'tendangan' => 'Tendangan'];
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

            {{-- Jarak antar kelompok dirapatkan setelah kotaknya diperbesar:
                 dua deret indikator 44px dan tiga deret hukuman 44px tidak muat
                 di setengah layar kalau jaraknya tetap selebar sebelumnya, dan
                 yang pertama terpotong justru Peringatan — deret yang paling
                 tidak boleh luput. --}}
            <div class="flex flex-col items-start gap-6">
                {{-- Indikator juri per teknik: yang terlihat operator adalah
                     berapa juri yang menekan teknik yang sama, karena itulah
                     yang menentukan sebuah nilai sah atau tidak. --}}
                <div class="flex flex-col items-center" x-show="peraturan.jumlah_juri">
                    @foreach ($teknik as $kunci => $label)
                    <div class="flex items-center gap-2">
                        <!-- <span class="silat-angka text-[10px] tracking-[.1em] {{ $redup }} uppercase">{{$label}}</span> -->
                        {{-- Kotak bernomor juri, bukan bulatan polos.
                             Operator yang melihat "dua bulatan menyala" masih
                             harus menghitung sendiri bulatan keberapa yang
                             menyala untuk tahu juri mana yang belum menekan --
                             dan itu justru yang ditanyakan wasit saat nilai
                             tidak terbit. J1/J2/J3 menjawabnya tanpa dihitung. --}}
                        {{-- Satu deret bersambung, bertepi lurus: dibaca dari
                             tepi matras, tiga kotak terpisah bersudut membulat
                             terbaca sebagai tiga benda yang tak berhubungan.
                             Bersambung, ia satu alat ukur — dan yang dicari
                             mata cuma bagian mana yang sudah menyala. --}}
                        <div class="flex border-[1.5px] {{ $tepi }}" aria-hidden="true">
                            <template x-for="i in peraturan.jumlah_juri" :key="i">
                                <span class="silat-angka grid h-11 w-[54px] shrink-0 place-items-center border-e-[1.5px] text-[20px] font-semibold last:border-e-0 {{ $tepi }} {{ $redup }}"
                                    x-bind:class="(indikatorTeknik?.{{ $sudut }}?.{{ $kunci }} ?? []).includes(i)
                                              ? 'bg-silat-teks text-silat-panel'
                                              : ''"
                                    x-text="'J' + i"></span>
                            </template>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{--
                    Deretan petak hukuman lebih lebar daripada layar ponsel.
                    Ia digulir di dalam wadahnya sendiri, bukan mendorong
                    seluruh halaman jadi lebih lebar dari viewport: badan
                    halaman yang ikut bergeser mendatar membuat tombol kendali
                    di kolom bawah tidak lagi sejajar dengan apa yang terlihat.
                --}}
                <div class="flex max-w-full items-center gap-2 overflow-x-auto">
                    <!-- <p class="silat-angka mb-2 text-[10px] tracking-[.12em] {{ $redup }} uppercase">Hukuman</p> -->

                    @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
                    @php($jatah = config("scoring.tanding.hukuman.{$jenis}.jumlah_kolom", 0))
                    <div class="mt-2 flex items-center gap-2.5 ">
                        <!-- <span class="w-[82px] shrink-0 text-[12.5px] {{ $kedua }}">{{ ucfirst($jenis) }}</span> -->
                        <div class="flex border-[1.5px] {{ $tepi }}" aria-hidden="true">
                            @for ($i = 1; $i <= $jatah; $i++)
                                {{-- Petak terakhir Peringatan berarti diskualifikasi.
                                     Penandanya kini tepi KIRI yang putus-putus, bukan
                                     kotak putus-putus yang berdiri sendiri: deretnya
                                     bersambung, jadi yang tersisa untuk dibedakan
                                     hanyalah garis pemisahnya. --}}
                                <span class="grid h-11 w-[54px] place-items-center border-e-[1.5px] last:border-e-0 {{ $tepi }}"
                                @class(['border-s-[1.5px] border-dashed'=> $jenis === 'peringatan' && $i === $jatah])
                                x-bind:class="(hukuman?.{{ $kunciSkor }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                                ? 'bg-silat-teks text-silat-panel'
                                : '{{ $redup }}'">
                                <x-silat.ikon-hukuman :jenis="$jenis" :tingkat="$i" :nyala="true" :ukuran="30"
                                    x-bind:class="(hukuman?.{{ $kunciSkor }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                                        ? ''
                                        : 'invert opacity-70'" />
                                </span>
                                @endfor
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