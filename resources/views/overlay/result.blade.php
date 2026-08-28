@php($sebabLabel = App\Support\Scoring\AlasanMenang::peta())

<x-layouts.overlay title="Papan hasil">
    {{--
        sebabLabel hidup di x-data ANAK, bukan disebar ke x-data induk lewat
        {...overlayLive(cfg), ...} -- penyebaran objek membekukan getter
        (tampilWaktu) jadi nilai statis sekali evaluasi, bukan menyalin
        definisi getter-nya. Ditemukan langsung lewat bug nyata di panel
        juri (lihat commit fa068e0); anak Alpine tetap bisa membaca `match`
        dari cakupan induknya tanpa masalah, jadi cukup ditambahkan di sini.

        Tiga hal yang dulu hilang dari papan ini:

        1. SUDUT pemenang tidak berbidang warna. Panelnya abu netral, dan
           satu-satunya penanda merah/biru adalah warna teks alasan menang.
           Penonton yang baru menyalakan siaran tidak tahu sudut mana yang
           menang tanpa membaca baris kecil di paling bawah -- baris yang
           hanya muncul kalau hasilnya sudah disahkan.
        2. SKOR akhir tidak ditampilkan sama sekali. Papan menyebut "menang
           angka mutlak" tanpa satu angka pun.
        3. Status pengesahan hanya muncul saat sudah sah. Kalau belum, papan
           diam -- padahal halaman gelanggang publik justru menyatakannya.
           Hasil yang belum disahkan bisa masih berubah, dan siaran adalah
           tempat terakhir yang boleh menyembunyikan itu.

        Emas dilepas dari kop "HASIL PARTAI". Ia dipakai untuk juara dan
        medali; satu partai penyisihan bukan keduanya.
    --}}
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai && match?.status === 'selesai'" x-cloak
             class="absolute top-1/2 left-1/2 flex w-[760px] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-silat"
             style="box-shadow: 0 16px 56px rgba(0,0,0,.5)">

            {{-- Bidang sudut penuh, seperti papan skor mana pun. Lebarnya tetap
                 supaya papan tidak bergeser saat pemenangnya berganti sudut. --}}
            <div class="flex w-[190px] shrink-0 flex-col items-center justify-center gap-1 px-4 py-8"
                 x-bind:class="match?.winner_corner === 'red' ? 'bg-silat-merah-dalam' : 'bg-silat-biru-dalam'">
                <p class="text-[13px] tracking-[.14em] text-silat-teks uppercase"
                   x-text="match?.winner_corner === 'red' ? 'Sudut Merah' : 'Sudut Biru'"></p>
                <p class="silat-angka text-[64px] leading-none font-medium text-silat-teks"
                   x-text="match?.winner_corner === 'red' ? skorTotal.merah : skorTotal.biru"></p>
                <p class="silat-angka text-[15px]"
                   x-bind:class="match?.winner_corner === 'red' ? 'text-silat-teks-merah-samar' : 'text-silat-teks-biru-samar'"
                   x-text="'lawan ' + (match?.winner_corner === 'red' ? skorTotal.biru : skorTotal.merah)"></p>
            </div>

            <div class="flex min-w-0 flex-1 flex-col justify-center gap-1 bg-silat-panel px-10 py-8">
                <p class="text-[13px] tracking-[.14em] text-silat-teks-redup uppercase">Hasil partai</p>

                <p class="truncate text-[34px] leading-tight font-medium text-silat-teks"
                   x-text="(match?.winner_corner === 'red' ? red : blue)?.nama"></p>
                <p class="truncate text-[18px] text-silat-teks-redup"
                   x-text="(match?.winner_corner === 'red' ? red : blue)?.kontingen"></p>

                <p x-data="{ sebabLabel: @js($sebabLabel) }"
                   class="mt-3 text-[17px] text-silat-teks"
                   x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></p>

                {{-- Selalu tampil, dua-duanya. Diam bukan jawaban di siaran. --}}
                <p class="mt-1 text-[14px]"
                   x-bind:class="match?.ratified ? 'text-silat-teks-redup' : 'text-silat-teguran'"
                   x-text="match?.ratified ? 'Hasil sudah disahkan Dewan Wasit Juri.' : 'Menunggu pengesahan Dewan Wasit Juri.'"></p>
            </div>
        </div>
    </div>
</x-layouts.overlay>
