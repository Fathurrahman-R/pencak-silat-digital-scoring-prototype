<x-layouts.overlay title="Rincian nilai &amp; hukuman">
    {{--
        Deret hukuman siaran — mengikuti `overlay-siaran.dc.html`.

        Dua blok bertumpuk di kiri atas, satu per sudut, masing-masing memuat
        ketiga tingkat lengkap dengan jumlah petaknya. Petak yang belum terisi
        digambar sebagai TEPI, bukan bidang: bidang tipis di atas warna sudut
        praktis tidak terlihat di siaran, dan penonton kehilangan justru
        informasi terpentingnya — berapa petak tersisa sebelum diskualifikasi.

        Jumlah petak dibaca dari config scoring, bukan ditulis ulang di sini,
        supaya tangga hukuman di layar penonton tidak pernah berbeda dari yang
        dihitung mesin scoring.
    --}}
    @php
    $tingkat = [
    'pembinaan' => config('scoring.tanding.hukuman.pembinaan.jumlah_kolom', 2),
    'teguran' => config('scoring.tanding.hukuman.teguran.jumlah_kolom', 2),
    'peringatan' => config('scoring.tanding.hukuman.peringatan.jumlah_kolom', 3),
    ];

    // Nama kelas ditulis utuh: kelas yang dirangkai dari variabel tidak
    // pernah dihasilkan Tailwind.
    $sisi = [
    ['merah', 'red', 'bg-silat-siaran-merah', 'text-silat-teks-merah-samar', 'border-silat-teks-merah-samar'],
    ['biru', 'blue', 'bg-silat-siaran-biru', 'text-silat-teks-biru-samar', 'border-silat-teks-biru-samar'],
    ];

    /*
    * Dua teknik saja di indikator juri. Jatuhan tidak ditekan juri --
    * nilainya mutlak dan diterbitkan wasit -- jadi deretnya tidak akan
    * pernah menyala, dan deret yang selamanya kosong terbaca penonton
    * sebagai juri yang tidak menekan, bukan sebagai teknik yang memang
    * bukan urusan juri.
    */
    $teknikJuri = ['pukulan' => 'Pukulan', 'tendangan' => 'Tendangan'];
    @endphp

    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai" x-cloak class="absolute top-[22px] left-0 flex flex-col"
            style="box-shadow: 0 8px 40px rgba(0,0,0,.4)">
            @foreach ($sisi as [$kunciSkor, $sudut, $bidang, $label, $tepi])
            <div class="{{ $bidang }} flex flex-col items-start gap-[7px] px-4.5 py-3.5 transition-opacity"
                x-bind:class="kilat === '{{ $sudut }}' ? 'silat-kilat' : ''">
                {{--
                    Indikator juri, satu deret per teknik yang dinilai juri.

                    Inilah satu-satunya bagian siaran yang menjelaskan MENGAPA
                    sebuah nilai terbit atau tidak: penonton yang melihat
                    serangan bersih tapi papan diam berhak tahu bahwa yang
                    sepakat memang belum cukup. Nomornya nomor TUGAS juri,
                    bukan identitas siapa pun — lihat JudgeInputReceived.

                    Ia menyala hanya selama jendela konsensus, lalu padam
                    sendiri; kotaknya sendiri tidak pernah hilang dari layar,
                    supaya penonton selalu tahu ada berapa juri.
                --}}
                <div class="flex flex-col">
                    @foreach ($teknikJuri as $kunciTeknik => $labelTeknik)
                    <div class="flex items-center gap-2.5">
                        <div class="flex border-[1.5px] {{ $tepi }}" aria-hidden="true">
                            <template x-for="i in (peraturan.jumlah_juri ?? 3)" :key="'{{ $sudut }}-{{ $kunciTeknik }}-' + i">
                                <span class="silat-angka grid h-9 w-[42px] shrink-0 place-items-center border-e-[1.5px] text-[17px] font-semibold last:border-e-0 {{ $tepi }} {{ $label }}"
                                    x-bind:class="(indikatorTeknik?.{{ $kunciSkor }}?.{{ $kunciTeknik }} ?? []).includes(i)
                                    ? 'bg-white text-silat-panel'
                                    : ''"
                                    x-text="'J' + i"></span>
                            </template>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex flex-row gap-2">
                    @foreach ($tingkat as $jenis => $jatah)
                    <div class="flex w-full items-center gap-2.5">
                        {{-- Deret bersambung bertepi lurus: di siaran ia
                                 dilihat sekilas dari kejauhan, dan kotak-kotak
                                 terpisah bersudut membulat memaksa mata
                                 menghitung benda dulu sebelum membaca isinya. --}}
                        <div class="flex border-[1.5px] {{ $tepi }}" aria-hidden="true">
                            @for ($i = 1; $i <= $jatah; $i++)
                                <span class="grid h-9 w-[42px] place-items-center border-e-[1.5px] last:border-e-0 {{ $tepi }} {{ $label }}"
                                @class(['border-s-[1.5px] border-dashed'=> $jenis === 'peringatan' && $i === $jatah])
                                x-bind:class="(hukuman.{{ $kunciSkor }}.{{ $jenis }} ?? 0) >= {{ $i }}
                                ? 'bg-white text-silat-panel'
                                : ''">
                                <x-silat.ikon-hukuman :jenis="$jenis" :tingkat="$i" :nyala="true" :ukuran="24"
                                    x-bind:class="(hukuman.{{ $kunciSkor }}.{{ $jenis }} ?? 0) >= {{ $i }}
                                                ? ''
                                                : 'invert opacity-70'" />
                                </span>
                                @endfor
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-layouts.overlay>
