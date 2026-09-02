{{--
    Layar juri menjawab verifikasi -- Pasal 13.

    MENGAMBIL ALIH panel juri sepenuhnya. Bukan lapisan di atas tombol nilai,
    bukan panel samping: tombol nilai harus benar-benar hilang dari DOM.
    Juri yang sedang diminta menjawab tidak boleh bisa memberi nilai untuk
    kejadian yang justru sedang dipertanyakan, dan lapisan transparan di atas
    tombol tetap menyisakan kemungkinan tekanan tembus lewat celah render.

    Tata letak mengikuti `panel-juri.dc.html` (mode verifikasi): merah kiri,
    biru kanan dengan lorong 28px seperti tombol nilai, dan "Tidak ada" sebagai
    batang selebar panel DI BAWAH keduanya, setinggi 64px. Menempatkannya di
    bawah, bukan di antara dua sudut, membuat jawaban "tidak ada" tidak pernah
    berada di jalur jempol yang sedang mengarah ke salah satu sudut.

    Landscape 844x390. Tidak boleh ada gulir.
--}}

<div class="flex h-full flex-col overflow-hidden bg-silat-latar px-4 pt-3.5 pb-4">
    <header class="flex shrink-0 items-center gap-2.5">
        <span class="silat-angka rounded-silat-kecil bg-silat-teks px-2 py-1 text-[10.5px] font-semibold tracking-[.14em] text-silat-latar uppercase">
            Verifikasi juri
        </span>
        <span class="silat-angka truncate text-[11px] text-silat-teks-samar"
              x-text="'Diminta ' + (verifikasi?.diminta_oleh ?? 'aparat pertandingan') + ' · Babak ' + (verifikasi?.round ?? '–')"></span>
        <span class="ml-auto shrink-0"><x-silat.indikator-koneksi /></span>
    </header>

    {{-- Pertanyaannya sendiri, sebesar mungkin: ini satu-satunya hal yang
         harus dibaca juri sebelum menekan. --}}
    <div class="shrink-0 pt-3">
        <p class="text-[21px] leading-[1.25] font-semibold tracking-[-0.02em] text-silat-teks"
           x-text="verifikasi?.pertanyaan"></p>
        <p class="pt-1 text-[12px] text-silat-teks-redup"
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

    {{-- BELUM MENJAWAB: dua sudut berdampingan, "tidak ada" sebagai batang di bawah. --}}
    <template x-if="! sudahMenjawabVerifikasi">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="grid min-h-0 flex-1 grid-cols-2 gap-x-[var(--silat-lorong-sudut)] pt-3">
                <button type="button"
                        x-on:click="jawabVerifikasi('red')"
                        class="flex flex-col items-center justify-center gap-1 rounded-silat bg-silat-merah px-5 text-center">
                    <span class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-merah-redup uppercase">Sudut merah</span>
                    <span class="line-clamp-2 text-[26px] leading-tight font-semibold tracking-[-0.02em] text-silat-teks"
                          x-text="match.red?.athletes?.join(', ') ?? '—'"></span>
                    <span class="line-clamp-1 text-[13px] text-silat-teks-merah-samar"
                          x-text="match.red?.contingent ?? ''"></span>
                </button>

                <button type="button"
                        x-on:click="jawabVerifikasi('blue')"
                        class="flex flex-col items-center justify-center gap-1 rounded-silat bg-silat-biru px-5 text-center">
                    <span class="silat-angka text-[11px] tracking-[.14em] text-silat-teks-biru uppercase">Sudut biru</span>
                    <span class="line-clamp-2 text-[26px] leading-tight font-semibold tracking-[-0.02em] text-silat-teks"
                          x-text="match.blue?.athletes?.join(', ') ?? '—'"></span>
                    <span class="line-clamp-1 text-[13px] text-silat-teks-biru-samar"
                          x-text="match.blue?.contingent ?? ''"></span>
                </button>
            </div>

            {{-- Tepi, bukan bidang: pilihan ini tidak menunjuk sudut mana pun. --}}
            <button type="button"
                    x-on:click="jawabVerifikasi('tidak_ada')"
                    class="mt-2.5 h-[var(--silat-sentuh-min)] shrink-0 rounded-silat border-[1.5px] border-silat-tepi-petak text-[17px] font-medium text-silat-teks-kedua"
                    x-text="verifikasi?.pilihan_tidak_ada ?? 'Tidak ada'"></button>
        </div>
    </template>

    <p class="shrink-0 pt-2 text-center text-[12px] text-silat-teks-samar">
        Jawabanmu tidak terlihat juri lain sampai ketiganya selesai.
    </p>
</div>
