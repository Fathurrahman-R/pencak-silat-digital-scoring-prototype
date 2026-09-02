@php
    $sebabLabel = App\Support\Scoring\AlasanMenang::peta();

    /*
     * Enam baris rincian, urut sesuai naskah: tiga teknik dulu, lalu tangga
     * hukuman. Ditulis sekali di sini, bukan enam blok Blade yang sama
     * bentuknya -- yang pertama kali berbeda tidak akan ada yang menyadarinya.
     *
     * Satu blok @php untuk seluruh berkas, bukan campuran bentuk sebaris
     * @php(...) dan bentuk blok: Blade tidak mengompilasi blok @php yang
     * berdiri di berkas yang sudah memakai bentuk sebaris, dan halamannya
     * membalas 500 tanpa menyebut sebabnya.
     */
    $barisRincian = [
        ['teknik', 'pukulan', 'Pukulan'],
        ['teknik', 'tendangan', 'Tendangan'],
        ['teknik', 'jatuhan', 'Jatuhan'],
        ['hukuman', 'pembinaan', 'Pembinaan'],
        ['hukuman', 'teguran', 'Teguran'],
        ['hukuman', 'peringatan', 'Peringatan'],
    ];
@endphp

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
             {{-- Diperkecil 0.82 dari ukuran rancangan, bukan digambar ulang
                  lebih kecil: rancangannya lahir tanpa tabel rincian, dan
                  dengan enam baris itu papannya memenuhi 87% kanvas siaran --
                  gambar kamera di baliknya praktis habis, dan tepi papan
                  nyaris menyentuh tepi layar. Skala menjaga seluruh rasio
                  huruf dan jarak tetap seperti rancangan. --}}
             class="absolute top-1/2 left-1/2 w-[1120px] origin-center -translate-x-1/2 -translate-y-1/2 scale-[.82] bg-silat-siaran-kertas"
             style="box-shadow: 0 20px 80px rgba(0,0,0,.55)">

            <div class="px-12 pt-10 pb-8 text-center">
                {{-- Urutan mengikuti rancangan: nomor partai dulu, lalu tahap
                     bagan, baru kelasnya. Nomor partai adalah yang dipakai
                     announcer dan papan jadwal untuk menyebut pertandingan
                     ini; tanpa itu, penonton yang memegang jadwal cetak tidak
                     bisa mencocokkan hasil yang baru saja tayang. --}}
                <p class="silat-angka text-[16px] font-bold tracking-[.2em] text-silat-latar uppercase"
                   x-text="[
                       match?.id ? 'Partai ' + match.id : null,
                       babakLabel,
                       kelas ? (kelas.jenis_kelamin + ' ' + kelas.golongan + ' — ' + kelas.nama) : null,
                   ].filter(Boolean).join(' · ')"></p>
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

            {{--
                RINCIAN: dari mana angka akhir itu datang.

                "Menang angka 21-14" tidak menjelaskan apa pun sampai penonton
                tahu 21 itu tersusun dari berapa pukulan, tendangan, dan
                jatuhan — dan berapa hukuman yang menggerusnya. Enam baris,
                urut sesuai naskah: tiga teknik dulu, lalu tangga hukuman.

                Yang nol ditulis "—" dan diredupkan, bukan "0": deretan angka
                nol menuntut pembacanya memindai dua kali untuk menemukan baris
                yang benar-benar berisi.
            --}}
            <div class="flex flex-col gap-1 px-12 pt-5.5 pb-10">
                @foreach ($barisRincian as [$sumber, $kunci, $label])
                    <div class="grid grid-cols-[1fr_300px_1fr] items-center border-t border-silat-siaran-kertas-redup py-[9px]">
                        <span class="silat-angka text-left text-[27px] leading-none font-medium"
                              x-bind:class="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) === 0 ? 'text-silat-teks-redup' : 'text-silat-latar'"
                              x-text="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) || '—'"></span>

                        {{-- Token PANITIA, bukan token gelanggang: papan ini
                             satu-satunya permukaan terang di overlay, dan
                             `silat-teks-*` dirancang untuk teks putih di atas
                             bidang gelap -- di atas kertas ia nyaris tak
                             terbaca. --}}
                        <span class="text-center text-[25px] leading-none tracking-[-0.01em] text-ink-secondary">{{ $label }}</span>

                        <span class="silat-angka text-right text-[27px] leading-none font-medium"
                              x-bind:class="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) === 0 ? 'text-silat-teks-redup' : 'text-silat-latar'"
                              x-text="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) || '—'"></span>
                    </div>
                @endforeach
            </div>

            {{-- Selalu tampil, dua-duanya. Diam bukan jawaban di siaran. --}}
            <p class="border-t border-silat-siaran-kertas-redup px-12 py-5 text-center text-[18px] text-silat-teks-samar"
               x-text="match?.ratified ? 'Hasil sudah disahkan Dewan Wasit Juri.' : 'Menunggu pengesahan Dewan Wasit Juri.'"></p>
        </div>
    </div>
</x-layouts.overlay>
