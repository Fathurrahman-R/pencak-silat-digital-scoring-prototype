{{--
    Verifikasi juri, dilihat dari meja operator — Pasal 13.

    Operator tidak menekan apa pun di sini: yang meminta adalah Wasit, yang
    menjawab juri, dan yang menerapkan Wasit lagi. Tapi selama verifikasi
    berjalan pertandingan BERHENTI, dan operator adalah orang yang ditanyai
    semua orang di sekitar meja — "kenapa berhenti", "sudah berapa juri",
    "jadinya siapa". Tanpa bagian ini, satu-satunya layar yang tahu jawabannya
    adalah tablet Wasit yang sedang dipegang di tengah matras.

    Isinya karena itu cuma bacaan: pertanyaannya, siapa sudah menjawab apa, dan
    hitungan suara tiap sudut.

    HASILNYA tidak ada di sini. Ia pindah ke modal <x-silat.verifikasi-hasil>,
    yang muncul begitu Wasit MENERAPKANNYA. Alasannya: hasil yang terbit di
    sudut layar begitu ambang tercapai terbaca sebagai keputusan yang sudah
    jadi, padahal ia masih menunggu Wasit -- dan yang membacanya di sekitar
    meja mengumumkannya lebih dulu. Yang pantas dilihat sepanjang polling
    berjalan cuma suaranya, dan suara belum keputusan.

    Jawaban tiap juri memang terlihat di sini. Yang disembunyikan naskah adalah
    jawaban juri dari SESAMA JURI supaya yang belum menjawab tidak ikut arus —
    dan StatePartaiPanel yang menegakkannya, dengan menyembunyikan label
    jawaban hanya dari juri partai ini. Operator, seperti Wasit, melihatnya.

    Muncul dan hilang sendiri mengikuti `verifikasiBerjalan`. Tingginya
    dibiarkan mengikuti isi, tidak `flex-1`: blok skor di atasnya menyusut
    seperlunya dan tetap terbaca.
--}}
<template x-if="verifikasiBerjalan">
    <div class="flex shrink-0 flex-col gap-3 border-t-2 border-silat-teks bg-silat-panel px-5 py-4 sm:px-7">

        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            {{-- Menyebut APA yang diverifikasi, bukan cuma bahwa ada
                 verifikasi. Itu pertanyaan pertama yang diajukan semua orang
                 di sekitar meja begitu pertandingan berhenti. --}}
            <span class="silat-angka rounded-silat-kecil bg-silat-teks px-2.5 py-1 text-[13px] font-semibold tracking-[.14em] text-silat-panel uppercase"
                  x-text="'Verifikasi ' + (verifikasi?.jenis_label ?? 'Juri')"></span>
            <span class="silat-angka text-[14px] text-silat-teks-samar"
                  x-text="'Diminta ' + (verifikasi?.diminta_oleh ?? 'aparat pertandingan') + ' · Babak ' + (verifikasi?.round ?? '–') + ' · pertandingan dihentikan'"></span>
        </div>

        <p class="text-[22px] leading-[1.25] font-semibold tracking-[-0.02em] text-silat-teks"
           x-text="verifikasi?.pertanyaan"></p>

        {{-- Dua lajur di layar lebar, bertumpuk di ponsel: kiri siapa menjawab
             apa, kanan hitungan dan hasilnya. --}}
        <div class="grid gap-4 lg:grid-cols-[1fr_minmax(280px,420px)]">

            {{-- Satu petak per juri, dengan nomornya di muka. Yang belum
                 menjawab digambar sebagai TEPI, sama seperti indikator juri di
                 blok sudut, jadi "berapa yang sudah masuk" terbaca dari
                 kejauhan tanpa membaca satu huruf pun. --}}
            <div class="flex flex-wrap gap-2">
                <template x-for="j in (verifikasi?.jawaban ?? [])" :key="j.judge_user_id">
                    <div class="flex min-w-[132px] flex-1 items-center gap-2.5 rounded-silat px-3 py-2.5"
                         x-bind:class="{
                             'bg-silat-merah-dalam': j.jawaban === 'red',
                             'bg-silat-biru-dalam': j.jawaban === 'blue',
                             'border-[1.5px] border-silat-tepi-petak': j.jawaban !== 'red' && j.jawaban !== 'blue',
                         }">
                        <span class="silat-angka grid size-10 shrink-0 place-items-center rounded-silat-kecil bg-silat-teks text-[18px] font-semibold text-silat-panel"
                              x-text="'J' + j.judge_number"></span>
                        <span class="min-w-0 flex-1 truncate text-[18px] font-medium text-silat-teks"
                              x-text="j.jawaban_label ?? 'Sudah menjawab'"></span>
                    </div>
                </template>

                <template x-for="m in (verifikasi?.menunggu ?? [])" :key="m.judge_user_id">
                    <div class="flex min-w-[132px] flex-1 items-center gap-2.5 rounded-silat border-[1.5px] border-dashed border-silat-tepi-petak px-3 py-2.5">
                        <span class="silat-angka grid size-10 shrink-0 place-items-center rounded-silat-kecil border-[1.5px] border-silat-tepi-petak text-[18px] font-semibold text-silat-teks-redup"
                              x-text="'J' + m.judge_number"></span>
                        <span class="min-w-0 flex-1 truncate text-[18px] text-silat-teks-redup">Menunggu</span>
                    </div>
                </template>
            </div>

            <div class="flex flex-col gap-2.5">
                <div class="flex gap-2">
                    @foreach ([
                        ['red', 'Merah', 'bg-silat-merah-dalam'],
                        ['tidak_ada', 'Tidak ada', 'border-[1.5px] border-silat-tepi-petak'],
                        ['blue', 'Biru', 'bg-silat-biru-dalam'],
                    ] as [$kunci, $judul, $gaya])
                        <div class="flex flex-1 flex-col items-center justify-center gap-0.5 rounded-silat {{ $gaya }} py-2.5">
                            <span class="text-[12px] tracking-[.1em] text-silat-teks uppercase">{{ $judul }}</span>
                            <span class="silat-angka text-[34px] leading-none font-semibold text-silat-teks tabular-nums"
                                  x-text="verifikasi?.hitungan?.['{{ $kunci }}'] ?? 0"></span>
                        </div>
                    @endforeach
                </div>

                {{-- Ambang tercapai: yang dinyatakan cuma bahwa suaranya sudah
                     cukup dan bolanya ada di Wasit. Sudut yang unggul TIDAK
                     disebut di sini -- hitungan di atas sudah menunjukkannya
                     apa adanya, dan menuliskannya sebagai kalimat membuatnya
                     terbaca sebagai keputusan yang sudah jadi. --}}
                <p x-show="verifikasi?.hasil" x-cloak
                   class="rounded-silat border-l-[4px] border-silat-teks bg-silat-latar px-3.5 py-2.5 text-[15px] leading-snug text-silat-teks-kedua">
                    Suara sudah cukup — menunggu Wasit menerapkannya.
                </p>

                <p x-show="! verifikasi?.hasil" x-cloak class="text-[15px] leading-relaxed text-silat-teks-redup">
                    Menunggu <span x-text="verifikasi?.ambang"></span> jawaban yang sama.
                    Jawaban juri yang belum masuk tidak lagi mengubahnya setelah ambang tercapai.
                </p>
            </div>
        </div>
    </div>
</template>
