<x-layouts.overlay title="Rincian nilai & hukuman">
    {{--
        Petak hukuman di sini dulu digambar sendiri: dua atau tiga <span>
        dengan `bg-white/15` untuk petak yang belum terisi. Di atas bidang
        panel itu terukur 1.54 — penonton siaran praktis tidak melihat petak
        kosongnya, jadi yang tampak cuma petak yang menyala, tanpa tahu ada
        berapa petak seluruhnya dan seberapa dekat pesilat ke diskualifikasi.

        Sekarang memakai <x-silat.pip-hukuman>, komponen yang sama dipakai
        scorebug. Petak kosongnya bertepi putih (9.36 di atas panel), dan
        jumlah petaknya dibaca dari config scoring — bukan ditulis ulang
        sebagai angka 2 dan 3 di berkas ini, yang akan diam-diam salah begitu
        tangga hukuman di config berubah.
    --}}
    @php
        $kolomHukuman = [
            'pembinaan' => ['jumlah' => config('scoring.tanding.hukuman.pembinaan.jumlah_kolom', 2)],
            'teguran' => ['jumlah' => config('scoring.tanding.hukuman.teguran.jumlah_kolom', 2)],
            'peringatan' => ['jumlah' => config('scoring.tanding.hukuman.peringatan.jumlah_kolom', 3)],
        ];
    @endphp

    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai" x-cloak class="absolute top-[64px] right-[64px] flex flex-col gap-3 rounded-silat bg-silat-panel p-5"
             style="width: 460px; box-shadow: 0 12px 40px rgba(0,0,0,.45)">
            @foreach (['merah' => 'red', 'biru' => 'blue'] as $kunciSkor => $sudut)
                @php($warnaSudut = $kunciSkor === 'merah' ? 'bg-silat-merah' : 'bg-silat-biru')
                <div
                    class="flex items-center gap-3 rounded-[4px] py-1.5 pr-2 transition-opacity"
                    x-bind:class="kilat === '{{ $sudut }}' ? 'silat-kilat bg-white/10' : ''"
                >
                    {{-- Batang sudut: satu-satunya penanda merah/biru di overlay ini,
                         karena namanya tidak membawa warna. --}}
                    <div class="w-[5px] shrink-0 self-stretch {{ $warnaSudut }}"></div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[16px] font-medium text-silat-teks" x-text="{{ $sudut }}?.nama"></p>
                        <x-silat.pip-hukuman :kolom="$kolomHukuman" :sisi="$kunciSkor" :ukuran="18" class="mt-1.5" />
                    </div>

                    <p class="silat-angka shrink-0 text-[32px] leading-none font-medium text-silat-teks" x-text="skorTotal.{{ $kunciSkor }}"></p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.overlay>
