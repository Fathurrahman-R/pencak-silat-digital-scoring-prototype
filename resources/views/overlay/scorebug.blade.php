<x-layouts.overlay title="Scorebug">
    {{--
        Scorebug bawah-tengah — mengikuti `overlay-siaran.dc.html`.

        Nol piksel yang bukan informasi: tidak ada bingkai hias, tidak ada
        lambang, tidak ada gradien, tidak ada sudut membulat. Bidang sudut
        memakai alpha 0.94 supaya gambar kamera tidak tertutup sepenuhnya,
        sementara teks di atasnya tetap lolos kontras.

        Deret hukuman TIDAK ikut di sini: ia berdiri sebagai lapisan sendiri di
        kiri atas (overlay “Rincian”), supaya operator vMix bisa memanggil
        keduanya terpisah dan scorebug tetap setinggi satu baris.
    --}}
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div
            x-show="adaPartai" x-cloak
            class="absolute bottom-[92px] left-1/2 flex -translate-x-1/2 items-stretch"
            style="box-shadow: 0 8px 40px rgba(0,0,0,.45)"
        >
            <div class="flex h-[108px] items-center gap-7 bg-silat-siaran-merah px-8">
                <div class="min-w-0 text-right">
                    <p class="truncate text-[22px] leading-[1.2] font-semibold tracking-[-0.01em] text-white"
                       x-text="red?.nama"></p>
                    <p class="silat-angka mt-0.5 truncate text-[14px] tracking-[.06em] text-silat-teks-merah-samar uppercase"
                       x-text="red?.kontingen"></p>
                </div>
                <p class="silat-angka min-w-[62px] text-right text-[44px] leading-none font-semibold text-white"
                   x-text="skorTotal.merah"></p>
            </div>

            <div class="flex h-[108px] w-[158px] flex-col items-center justify-center gap-[3px] bg-silat-siaran-tengah">
                <p class="silat-angka text-[30px] leading-none font-semibold text-white" x-text="tampilWaktu"></p>
                <p class="silat-angka text-[13px] tracking-[.12em] text-silat-teks-redup"
                   x-show="match?.current_round"
                   x-text="'BABAK ' + match?.current_round + (jumlahBabak ? '/' + jumlahBabak : '')"></p>
            </div>

            <div class="flex h-[108px] items-center gap-7 bg-silat-siaran-biru px-8">
                <p class="silat-angka min-w-[62px] text-[44px] leading-none font-semibold text-white"
                   x-text="skorTotal.biru"></p>
                <div class="min-w-0">
                    <p class="truncate text-[22px] leading-[1.2] font-semibold tracking-[-0.01em] text-white"
                       x-text="blue?.nama"></p>
                    <p class="silat-angka mt-0.5 truncate text-[14px] tracking-[.06em] text-silat-teks-biru-samar uppercase"
                       x-text="blue?.kontingen"></p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.overlay>
