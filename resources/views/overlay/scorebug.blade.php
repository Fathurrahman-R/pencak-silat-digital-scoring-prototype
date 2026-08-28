<x-layouts.overlay title="Scorebug">
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div
            x-show="adaPartai" x-cloak
            class="absolute bottom-[64px] left-1/2 flex -translate-x-1/2 items-stretch overflow-hidden rounded-silat"
            style="box-shadow: 0 12px 40px rgba(0,0,0,.45)"
        >
            {{--
                Deret hukuman ikut tampil di scorebug, bukan hanya di overlay
                breakdown yang terpisah. Tanpanya penonton siaran melihat skor
                melompat turun 5 atau 10 angka tanpa sebab apa pun di layar —
                satu-satunya penjelasan ada di overlay lain yang mungkin sedang
                tidak dipanggil operator vMix.

                Bentuknya kolom kecil, bukan angka, supaya tidak bersaing dengan
                skor: makin berat sanksinya makin putih kolomnya, mengikuti
                aturan yang sudah dipakai blok sudut.
            --}}
            @php
                $kolomHukuman = [
                    'pembinaan' => ['jumlah' => config('scoring.tanding.hukuman.pembinaan.jumlah_kolom', 2), 'nyala' => 'bg-white/45'],
                    'teguran' => ['jumlah' => config('scoring.tanding.hukuman.teguran.jumlah_kolom', 2), 'nyala' => 'bg-white/75'],
                    'peringatan' => ['jumlah' => config('scoring.tanding.hukuman.peringatan.jumlah_kolom', 3), 'nyala' => 'bg-white'],
                ];
            @endphp

            <div class="flex w-[420px] items-center justify-between bg-silat-merah px-6 py-4">
                <div class="min-w-0">
                    <p class="truncate text-[22px] font-medium text-silat-teks" x-text="red?.nama"></p>
                    <p class="truncate text-[15px] text-[#fff0f0]" x-text="red?.kontingen"></p>
                    <x-silat.pip-hukuman :kolom="$kolomHukuman" sisi="merah" class="mt-1.5" />
                </div>
                <p class="silat-angka pl-4 text-[44px] leading-none font-medium text-silat-teks" x-text="skorTotal.merah"></p>
            </div>

            <div class="flex w-[200px] flex-col items-center justify-center bg-silat-panel px-4 py-4">
                <p class="text-[12px] tracking-[.1em] text-silat-teks-redup" x-text="babakLabel"></p>
                <p class="silat-angka text-[30px] leading-none font-medium text-silat-teks" x-text="tampilWaktu"></p>
                <p class="silat-angka text-[12px] tracking-[.08em] text-silat-teks-redup"
                   x-show="match?.current_round"
                   x-text="'BABAK ' + match?.current_round + (jumlahBabak ? '/' + jumlahBabak : '')"></p>
            </div>

            <div class="flex w-[420px] items-center justify-between bg-silat-biru px-6 py-4">
                <p class="silat-angka pr-4 text-[44px] leading-none font-medium text-silat-teks" x-text="skorTotal.biru"></p>
                <div class="min-w-0 text-right">
                    <p class="truncate text-[22px] font-medium text-silat-teks" x-text="blue?.nama"></p>
                    <p class="truncate text-[15px] text-[#cbdbf7]" x-text="blue?.kontingen"></p>
                    <x-silat.pip-hukuman :kolom="$kolomHukuman" sisi="biru" rata="kanan" class="mt-1.5" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.overlay>
