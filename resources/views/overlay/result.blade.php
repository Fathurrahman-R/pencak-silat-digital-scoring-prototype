@php
    $sebabLabel = [
        'angka' => 'Menang Angka', 'teknik' => 'Menang Teknik', 'mutlak' => 'Menang Mutlak',
        'wmp' => 'Menang WMP', 'undur_diri' => 'Menang Undur Diri', 'cedera' => 'Menang (Cedera)',
        'wo' => 'Menang WO', 'diskualifikasi' => 'Menang Diskualifikasi',
    ];
@endphp

<x-layouts.overlay title="Papan hasil">
    {{--
        sebabLabel hidup di x-data ANAK, bukan disebar ke x-data induk lewat
        {...overlayLive(cfg), ...} -- penyebaran objek membekukan getter
        (tampilWaktu) jadi nilai statis sekali evaluasi, bukan menyalin
        definisi getter-nya. Ditemukan langsung lewat bug nyata di panel
        juri (lihat commit fa068e0); anak Alpine tetap bisa membaca `match`
        dari cakupan induknya tanpa masalah, jadi cukup ditambahkan di sini.
    --}}
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai && match?.status === 'selesai'" x-cloak
             class="absolute top-1/2 left-1/2 flex w-[720px] -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-2 rounded-silat bg-silat-panel px-10 py-8 text-center"
             style="box-shadow: 0 16px 56px rgba(0,0,0,.5)">
            <p class="text-[13px] tracking-[.14em] text-silat-emas">HASIL PARTAI</p>

            <p class="text-[34px] font-medium text-silat-teks"
               x-text="(match?.winner_corner === 'red' ? red : blue)?.nama"></p>
            <p class="text-[18px] text-silat-teks-redup"
               x-text="(match?.winner_corner === 'red' ? red : blue)?.kontingen"></p>

            <p x-data="{ sebabLabel: @js($sebabLabel) }"
               class="silat-angka mt-2 text-[15px] tracking-wide text-silat-teks"
               x-bind:class="match?.winner_corner === 'red' ? 'text-[#f5afb2]' : 'text-[#9ebbea]'"
               x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></p>

            <p class="mt-3 text-[13px]" x-show="match?.ratified" x-text="'Sudut '+(match?.winner_corner==='red'?'Merah':'Biru')+' · Sah'"></p>
        </div>
    </div>
</x-layouts.overlay>
