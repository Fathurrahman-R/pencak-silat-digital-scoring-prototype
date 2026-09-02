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
    @endphp

    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai" x-cloak class="absolute top-[22px] left-0 flex flex-col"
             style="box-shadow: 0 8px 40px rgba(0,0,0,.4)">
            @foreach ($sisi as [$kunciSkor, $sudut, $bidang, $label, $tepi])
                <div class="{{ $bidang }} flex flex-col items-start gap-[7px] px-4.5 py-3.5 transition-opacity"
                     x-bind:class="kilat === '{{ $sudut }}' ? 'silat-kilat' : ''">
                    @foreach ($tingkat as $jenis => $jatah)
                        <div class="flex w-full items-center gap-2.5">
                            <span class="silat-angka w-[106px] shrink-0 text-[12px] tracking-[.08em] {{ $label }} uppercase">{{ $jenis }}</span>
                            <div class="flex gap-1" aria-hidden="true">
                                @for ($i = 1; $i <= $jatah; $i++)
                                    <span class="grid h-[22px] w-[26px] place-items-center rounded-silat-kecil border-[1.5px] {{ $tepi }} {{ $label }}"
                                          @class(['border-dashed' => $jenis === 'peringatan' && $i === $jatah])
                                          x-bind:class="(hukuman.{{ $kunciSkor }}.{{ $jenis }} ?? 0) >= {{ $i }}
                                              ? 'bg-white border-white text-silat-panel'
                                              : ''">
                                        <x-silat.ikon :nama="$jenis" :ukuran="11" :label="null" />
                                    </span>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.overlay>
