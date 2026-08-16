<x-layouts.overlay title="Scorebug">
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div
            x-show="adaPartai" x-cloak
            class="absolute bottom-[64px] left-1/2 flex -translate-x-1/2 items-stretch overflow-hidden rounded-silat"
            style="box-shadow: 0 12px 40px rgba(0,0,0,.45)"
        >
            <div class="flex w-[420px] items-center justify-between bg-silat-merah px-6 py-4">
                <div class="min-w-0">
                    <p class="truncate text-[22px] font-medium text-silat-teks" x-text="red?.nama"></p>
                    <p class="truncate text-[15px] text-[#fbd9da]" x-text="red?.kontingen"></p>
                </div>
                <p class="silat-angka pl-4 text-[44px] leading-none font-medium text-silat-teks" x-text="skorTotal.merah"></p>
            </div>

            <div class="flex w-[200px] flex-col items-center justify-center bg-silat-panel px-4 py-4">
                <p class="text-[13px] tracking-[.1em] text-silat-teks-redup" x-text="babakLabel"></p>
                <p class="silat-angka text-[30px] leading-none font-medium text-silat-teks" x-text="tampilWaktu"></p>
            </div>

            <div class="flex w-[420px] items-center justify-between bg-silat-biru px-6 py-4">
                <p class="silat-angka pr-4 text-[44px] leading-none font-medium text-silat-teks" x-text="skorTotal.biru"></p>
                <div class="min-w-0 text-right">
                    <p class="truncate text-[22px] font-medium text-silat-teks" x-text="blue?.nama"></p>
                    <p class="truncate text-[15px] text-[#cbdbf7]" x-text="blue?.kontingen"></p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.overlay>
