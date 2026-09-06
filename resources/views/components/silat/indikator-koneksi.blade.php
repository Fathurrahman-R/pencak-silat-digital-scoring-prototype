{{--
    Penanda sambungan gelanggang, dipakai seluruh panel dan halaman live.

    Kedua keadaan sengaja TIDAK setara bobotnya. Tersambung adalah keadaan
    normal dan tidak perlu menarik perhatian, jadi ia titik kecil berikut satu
    kata. Terputus adalah keadaan yang menghentikan pekerjaan — nilai yang
    ditekan tidak sampai ke server — jadi ia tampil lebih besar, berkedip
    pelan, dan menyebutkan apa yang harus dilakukan.

    Terputus TIDAK berbidang merah. Merah hanya berarti sudut pesilat
    (BRIEF §2.2), dan sebuah peringatan merah di panel yang separuh layarnya
    memang merah adalah cara tercepat membuat aparat salah baca. Bedanya
    dibawa titik yang padam jadi tepi abu, teks yang lebih tebal, dan kalimat
    yang menyebut akibatnya.
--}}

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-live="polite">
    <span x-show="$store.koneksi.tersambung" x-cloak class="flex items-center gap-2">
        <span class="size-2 shrink-0 rounded-full bg-silat-hidup"></span>
        <span class="silat-angka text-[11.5px] text-silat-teks-redup">tersambung</span>
    </span>

    <span x-show="! $store.koneksi.tersambung" x-cloak
          class="flex items-center gap-2.5 rounded-silat border border-silat-tepi-petak px-3 py-2 motion-safe:animate-pulse">
        <span class="size-2 shrink-0 rounded-full border-[1.5px] border-silat-tepi-petak"></span>
        <span class="text-[14px] font-semibold text-silat-teks">Terputus</span>
        <span class="hidden text-[12px] text-silat-teks-kedua sm:inline">
            nilai belum tersimpan — periksa jaringan gelanggang
        </span>
    </span>

    {{--
        Tekanan yang sedang ditahan panel karena jaringannya putus.

        Ditampilkan terpisah dari penanda sambungan, dan tetap terlihat
        beberapa saat sesudah tersambung lagi sampai antreannya habis: yang
        perlu diketahui juri bukan "jaringan sudah kembali" melainkan "nilai
        saya sudah sampai". Panel yang tidak punya antrean (papan, overlay,
        halaman live) tidak pernah memunculkannya.
    --}}
    {{-- `$data.` bukan nama telanjang: panel live dan overlay memakai komponen
         Alpine lain yang tidak punya antrean, dan menyebut namanya begitu saja
         melempar ReferenceError di sana -- `typeof` pun tidak menolongnya. --}}
    <span x-show="($data.antreanTertahan ?? 0) > 0" x-cloak
          class="flex items-center gap-2 rounded-silat border border-silat-tepi-petak px-3 py-2">
        <span class="silat-angka text-[13px] font-semibold text-silat-teks" x-text="$data.antreanTertahan"></span>
        <span class="text-[12px] text-silat-teks-kedua">tekanan ditahan, dikirim sendiri</span>
    </span>
</div>
