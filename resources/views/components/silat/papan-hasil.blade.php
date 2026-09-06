@php
    /*
     * Papan hasil partai — kembaran panel dari `overlay/result.blade.php`.
     *
     * Sampai sekarang rincian skor hanya hidup di dua tempat: overlay siaran
     * dan berita acara PDF. Petugas gelanggang yang ingin tahu dari mana angka
     * akhirnya datang harus membuka vMix atau mencetak berkas — di tengah
     * kejuaraan, keduanya bukan jawaban.
     *
     * Yang ditambahkan di sini dan TIDAK ada di overlay: skor per babak.
     * Siaran menayangkannya beberapa detik lalu berganti, jadi ia memang hanya
     * boleh memuat yang paling penting. Panel dibaca selama masih diperlukan,
     * dan pertanyaan pertama sesudah partai selesai hampir selalu "babak mana
     * yang menentukan".
     *
     * Seluruh angkanya datang dari state Alpine `partaiPanel` yang sudah ada —
     * tidak ada properti statis, karena papan ini juga harus benar saat Dewan
     * Wasit Juri membatalkan sebuah nilai sementara layarnya terbuka.
     *
     * Satu blok @php untuk seluruh berkas: Blade tidak mengompilasi blok @php
     * yang berdiri di berkas yang sudah memakai bentuk sebaris @php(...).
     */
    $sebabLabel = App\Support\Scoring\AlasanMenang::peta();

    // Urut sesuai naskah: tiga nilai prestasi teknik dulu (Pasal 11.6.e),
    // lalu tangga hukuman (Pasal 11.6.d.4).
    $barisRincian = [
        ['teknik', 'pukulan', 'Pukulan'],
        ['teknik', 'tendangan', 'Tendangan'],
        ['teknik', 'jatuhan', 'Jatuhan'],
        ['hukuman', 'pembinaan', 'Pembinaan'],
        ['hukuman', 'teguran', 'Teguran'],
        ['hukuman', 'peringatan', 'Peringatan'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-silat-besar border border-silat-garis bg-silat-panel']) }}
     x-data="{ sebabLabel: @js($sebabLabel) }">

    {{-- Pemenang dan sebabnya. Sudut ditandai bidang warna penuh, bukan warna
         teks: yang membaca papan ini sering berdiri, bukan duduk di depannya. --}}
    <div class="flex items-center justify-between gap-4 border-b border-silat-garis px-5 py-4">
        <div class="min-w-0">
            <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Hasil partai</p>
            <p class="mt-1 truncate text-[15px] leading-[1.3] font-semibold text-silat-teks"
               x-text="sebabLabel[match?.win_reason] ?? match?.win_reason ?? 'Belum ditetapkan'"></p>
        </div>

        <template x-if="match?.winner_registration_id">
            <span class="shrink-0 rounded-silat px-3 py-1.5 text-[12px] font-semibold tracking-[.06em] uppercase"
                  x-bind:class="match?.winner_registration_id === match?.red?.registration_id
                      ? 'bg-silat-merah-dalam text-silat-teks-merah'
                      : 'bg-silat-biru-dalam text-silat-teks-biru'"
                  x-text="match?.winner_registration_id === match?.red?.registration_id ? 'Sudut merah' : 'Sudut biru'"></span>
        </template>
    </div>

    {{-- Skor akhir. --}}
    <div class="grid grid-cols-[1fr_auto_1fr] items-center px-5 py-4">
        <p class="silat-angka text-left text-[44px] leading-none font-semibold text-silat-teks-merah"
           x-text="skorTotal.merah"></p>
        <p class="silat-angka px-4 text-[10px] font-bold tracking-[.14em] text-silat-teks-redup uppercase">Poin akhir</p>
        <p class="silat-angka text-right text-[44px] leading-none font-semibold text-silat-teks-biru"
           x-text="skorTotal.biru"></p>
    </div>

    {{--
        Skor per babak.

        Angkanya sudah ada di `rounds[]` sejak endpoint state dibuat — tiap
        baris membawa skor_merah dan skor_biru babaknya. Yang belum ada hanya
        tempat untuk membacanya.

        Babak yang belum dimainkan tidak digambar sama sekali, bukan digambar
        sebagai 0–0: nol yang berarti "belum" dan nol yang berarti "tidak ada
        nilai" tidak boleh terlihat sama.
    --}}
    <div class="border-t border-silat-garis px-5 py-3">
        <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Skor per babak</p>

        <div class="mt-2 flex flex-col gap-px">
            <template x-for="babak in rounds.filter(r => r.status !== 'belum_mulai')" :key="babak.round">
                <div class="grid grid-cols-[1fr_auto_1fr] items-center py-1.5">
                    <span class="silat-angka text-left text-[19px] leading-none font-medium text-silat-teks-merah"
                          x-text="babak.skor_merah"></span>
                    <span class="px-4 text-[12px] leading-none text-silat-teks-kedua"
                          x-text="'Babak ' + babak.round"></span>
                    <span class="silat-angka text-right text-[19px] leading-none font-medium text-silat-teks-biru"
                          x-text="babak.skor_biru"></span>
                </div>
            </template>
        </div>
    </div>

    {{--
        RINCIAN: dari mana angka akhir itu datang.

        "Menang angka 21–14" tidak menjelaskan apa pun sampai pembacanya tahu
        21 itu tersusun dari berapa pukulan, tendangan, dan jatuhan — dan
        berapa hukuman yang menggerusnya.

        Yang nol ditulis "—" dan diredupkan, bukan "0": deretan angka nol
        menuntut pembacanya memindai dua kali untuk menemukan baris yang
        benar-benar berisi.
    --}}
    <div class="border-t border-silat-garis px-5 py-3">
        <p class="silat-angka text-[10px] tracking-[.14em] text-silat-teks-redup uppercase">Rincian</p>

        <div class="mt-2 flex flex-col gap-px">
            @foreach ($barisRincian as [$sumber, $kunci, $label])
                <div class="grid grid-cols-[1fr_auto_1fr] items-center py-1.5">
                    <span class="silat-angka text-left text-[17px] leading-none font-medium"
                          x-bind:class="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) === 0 ? 'text-silat-teks-redup' : 'text-silat-teks-merah'"
                          x-text="({{ $sumber }}?.merah?.{{ $kunci }} ?? 0) || '—'"></span>

                    <span class="px-4 text-center text-[12px] leading-none text-silat-teks-kedua">{{ $label }}</span>

                    <span class="silat-angka text-right text-[17px] leading-none font-medium"
                          x-bind:class="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) === 0 ? 'text-silat-teks-redup' : 'text-silat-teks-biru'"
                          x-text="({{ $sumber }}?.biru?.{{ $kunci }} ?? 0) || '—'"></span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Selalu tampil, dua-duanya. Hasil yang belum disahkan masih bisa
         berubah, dan panel gelanggang tidak boleh menyembunyikan itu. --}}
    <p class="border-t border-silat-garis px-5 py-3 text-center text-[12px] text-silat-teks-kedua"
       x-text="match?.ratified ? 'Hasil sudah disahkan Dewan Wasit Juri.' : 'Menunggu pengesahan Dewan Wasit Juri.'"></p>
</div>
