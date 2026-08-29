{{--
    Layar juri menjawab verifikasi -- Pasal 13.

    MENGAMBIL ALIH panel juri sepenuhnya. Bukan lapisan di atas tombol nilai,
    bukan panel samping: tombol nilai harus benar-benar hilang dari DOM.
    Juri yang sedang diminta menjawab tidak boleh bisa memberi nilai untuk
    kejadian yang justru sedang dipertanyakan, dan lapisan transparan di atas
    tombol tetap menyisakan kemungkinan tekanan tembus lewat celah render.

    Tata letak mengikuti tombol nilai yang biasa dipakai juri: merah kiri,
    biru kanan, lorong lebar di tengah. Selip jempol ke samping di sini
    berakibat sama mahalnya seperti di panel nilai -- ia memberikan jatuhan
    kepada lawan. Karena itu "Tidak ada" ditaruh DI TENGAH sebagai pemisah
    fisik, bukan sebagai tombol ketiga yang berjajar di pinggir.

    Landscape 844x390. Tidak boleh ada gulir.
--}}

<div class="flex h-full flex-col overflow-hidden bg-silat-latar">
    <header class="flex shrink-0 items-center justify-between gap-4 px-3 py-1.5">
        <p class="text-[13px] font-semibold text-silat-teks">
            Verifikasi — diminta <span x-text="verifikasi?.diminta_oleh ?? 'aparat pertandingan'"></span>
        </p>
        <div class="flex items-center gap-3">
            <span class="silat-angka text-[11px] text-silat-teks-redup"
                  x-text="'Babak ' + (verifikasi?.round ?? '–')"></span>
            <x-silat.indikator-koneksi />
        </div>
    </header>

    {{-- Pertanyaannya sendiri, sebesar mungkin: ini satu-satunya hal yang
         harus dibaca juri sebelum menekan. --}}
    <div class="shrink-0 px-3 pb-1 text-center">
        <p class="text-[22px] leading-tight font-semibold text-silat-teks" x-text="verifikasi?.pertanyaan"></p>
        <p class="text-[12px] text-silat-teks-redup"
           x-show="verifikasi?.tingkat_pelanggaran_label"
           x-text="'Sanksi yang akan dijatuhkan: ' + (verifikasi?.tingkat_pelanggaran_label ?? '')"></p>
    </div>

    {{-- SUDAH MENJAWAB: tombol hilang sama sekali. --}}
    <template x-if="sudahMenjawabVerifikasi">
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 p-4 text-center">
            <p class="text-[20px] font-semibold text-silat-teks">Jawabanmu sudah masuk</p>
            <p class="max-w-[52ch] text-[15px] leading-relaxed text-silat-teks-redup">
                Menunggu juri lain. Panel ini kembali menerima nilai begitu Wasit menerapkan hasilnya.
            </p>
            <p class="silat-angka text-[13px] text-silat-teks-redup"
               x-show="verifikasi?.menunggu?.length"
               x-text="'Masih ditunggu: ' + (verifikasi?.menunggu ?? []).map(m => m.sebutan).join(', ')"></p>
        </div>
    </template>

    {{-- BELUM MENJAWAB: tiga pilihan. --}}
    <template x-if="! sudahMenjawabVerifikasi">
        <div class="grid min-h-0 flex-1 grid-cols-[1fr_auto_1fr] gap-x-6 p-2">
            <button type="button"
                    x-on:click="jawabVerifikasi('red')"
                    class="flex flex-col items-center justify-center gap-1 rounded-silat bg-silat-merah-dalam px-4 text-center">
                <span class="text-[13px] tracking-[.14em] text-silat-teks uppercase">Sudut merah</span>
                <span class="line-clamp-2 text-[20px] leading-tight font-semibold text-silat-teks"
                      x-text="match.red?.athletes?.join(', ') ?? '—'"></span>
                <span class="line-clamp-1 text-[13px] text-silat-teks-merah-samar"
                      x-text="match.red?.contingent ?? ''"></span>
            </button>

            {{--
                Pemisah fisik, bukan tombol ketiga di pinggir. Lebarnya cukup
                untuk jadi jarak antar dua sudut sekaligus jadi sasaran yang
                sah -- juri yang benar-benar melihat "tidak ada" menekan
                tengah, dan tengah adalah tempat yang paling sulit dicapai
                secara tidak sengaja oleh jempol yang mengarah ke salah satu
                sudut.
            --}}
            <button type="button"
                    x-on:click="jawabVerifikasi('tidak_ada')"
                    class="flex w-[150px] flex-col items-center justify-center gap-1 rounded-silat border border-silat-tepi-kendali px-3 text-center">
                <span class="text-[13px] tracking-[.14em] text-silat-teks-redup uppercase">Tidak ada</span>
                <span class="text-[14px] leading-snug text-silat-teks"
                      x-text="verifikasi?.pilihan_tidak_ada ?? 'Tidak ada'"></span>
            </button>

            <button type="button"
                    x-on:click="jawabVerifikasi('blue')"
                    class="flex flex-col items-center justify-center gap-1 rounded-silat bg-silat-biru-dalam px-4 text-center">
                <span class="text-[13px] tracking-[.14em] text-silat-teks uppercase">Sudut biru</span>
                <span class="line-clamp-2 text-[20px] leading-tight font-semibold text-silat-teks"
                      x-text="match.blue?.athletes?.join(', ') ?? '—'"></span>
                <span class="line-clamp-1 text-[13px] text-silat-teks-biru-samar"
                      x-text="match.blue?.contingent ?? ''"></span>
            </button>
        </div>
    </template>

    <p class="shrink-0 px-3 pb-1.5 text-center text-[12px] text-silat-teks-redup">
        Jawabanmu tidak terlihat juri lain sampai ketiganya selesai.
    </p>
</div>
