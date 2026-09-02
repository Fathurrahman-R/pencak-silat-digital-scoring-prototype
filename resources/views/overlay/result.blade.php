@php($sebabLabel = App\Support\Scoring\AlasanMenang::peta())

<x-layouts.overlay title="Papan hasil">
    {{--
        Papan hasil siaran — mengikuti `overlay-siaran.dc.html`.

        Satu-satunya permukaan TERANG di seluruh overlay, dan itu disengaja: ia
        tampil beberapa detik sesudah operator mengakhiri partai, dan bidang
        terang memisahkannya tegas dari scorebug yang menempel sepanjang
        siaran.

        Tiga hal yang wajib ada di sini:

        1. SUDUT pemenang berbidang warna penuh, bukan sekadar warna teks —
           penonton yang baru menyalakan siaran harus tahu sudut mana yang
           menang tanpa membaca baris kecil.
        2. SKOR akhir kedua sudut, bukan hanya kalimat "menang angka".
        3. Status pengesahan, dua-duanya. Hasil yang belum disahkan masih bisa
           berubah, dan siaran adalah tempat terakhir yang boleh
           menyembunyikan itu.

        Alasan menang selalu bentuk terbaca — "Menang angka", bukan kode
        mentah. Emas tidak dipakai di sini: ia milik juara dan medali, dan satu
        partai penyisihan bukan keduanya.
    --}}
    <div x-data="overlayLive(@js($config))" class="relative h-full w-full">
        <div x-show="adaPartai && match?.status === 'selesai'" x-cloak
             x-data="{ sebabLabel: @js($sebabLabel) }"
             class="absolute top-1/2 left-1/2 w-[1120px] -translate-x-1/2 -translate-y-1/2 bg-silat-siaran-kertas"
             style="box-shadow: 0 20px 80px rgba(0,0,0,.55)">

            <div class="px-12 pt-10 pb-8 text-center">
                <p class="silat-angka text-[16px] font-bold tracking-[.2em] text-silat-latar uppercase"
                   x-text="[kelas ? (kelas.jenis_kelamin + ' ' + kelas.golongan + ' — ' + kelas.nama) : null, babakLabel].filter(Boolean).join(' · ')"></p>
                <p class="silat-angka mt-6.5 text-[22px] font-semibold tracking-[.12em] text-silat-latar uppercase"
                   x-text="sebabLabel[match?.win_reason] ?? match?.win_reason"></p>
            </div>

            {{-- Bidang sudut penuh untuk pemenang, bidang kertas redup untuk
                 yang kalah. Lebar keduanya sama supaya papan tidak bergeser
                 saat pemenangnya berganti sudut. --}}
            <div class="flex items-stretch">
                <div class="flex-1 px-11 pt-6.5 pb-7 text-left"
                     x-bind:class="match?.winner_corner === 'red' ? 'bg-silat-merah-dalam' : 'bg-silat-siaran-kertas-redup'">
                    <p class="text-[38px] leading-[1.1] tracking-[-0.025em]"
                       x-bind:class="match?.winner_corner === 'red' ? 'font-bold text-white' : 'font-semibold text-silat-garis'"
                       x-text="red?.nama"></p>
                    <p class="mt-1.5 text-[21px] leading-[1.3]"
                       x-bind:class="match?.winner_corner === 'red' ? 'text-silat-teks-merah-redup' : 'text-silat-teks-samar'"
                       x-text="red?.kontingen"></p>
                </div>

                <div class="flex-1 px-11 pt-6.5 pb-7 text-right"
                     x-bind:class="match?.winner_corner === 'blue' ? 'bg-silat-biru-dalam' : 'bg-silat-siaran-kertas-redup'">
                    <p class="text-[38px] leading-[1.1] tracking-[-0.025em]"
                       x-bind:class="match?.winner_corner === 'blue' ? 'font-bold text-white' : 'font-semibold text-silat-garis'"
                       x-text="blue?.nama"></p>
                    <p class="mt-1.5 text-[21px] leading-[1.3]"
                       x-bind:class="match?.winner_corner === 'blue' ? 'text-silat-teks-biru' : 'text-silat-teks-samar'"
                       x-text="blue?.kontingen"></p>
                </div>
            </div>

            <div class="grid grid-cols-[1fr_auto_1fr] items-center px-12 pt-8 pb-2">
                <p class="silat-angka text-left text-[108px] leading-[0.9] font-semibold"
                   x-bind:class="match?.winner_corner === 'red' ? 'text-silat-latar' : 'text-silat-teks-redup'"
                   x-text="skorTotal.merah"></p>
                <p class="silat-angka px-7 text-[18px] font-bold tracking-[.16em] text-silat-latar uppercase">Poin akhir</p>
                <p class="silat-angka text-right text-[108px] leading-[0.9] font-semibold"
                   x-bind:class="match?.winner_corner === 'blue' ? 'text-silat-latar' : 'text-silat-teks-redup'"
                   x-text="skorTotal.biru"></p>
            </div>

            {{-- Selalu tampil, dua-duanya. Diam bukan jawaban di siaran. --}}
            <p class="border-t border-silat-siaran-kertas-redup px-12 py-5 text-center text-[18px] text-silat-teks-samar"
               x-text="match?.ratified ? 'Hasil sudah disahkan Dewan Wasit Juri.' : 'Menunggu pengesahan Dewan Wasit Juri.'"></p>
        </div>
    </div>
</x-layouts.overlay>
