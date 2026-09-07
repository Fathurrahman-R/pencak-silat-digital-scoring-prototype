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

    SATU pengecualian atas aturan itu: titik mutu jaringan di keadaan
    tersambung memakai `--silat-awas` saat latensinya buruk. Pengecualian ini
    diputuskan sadar, dan ronanya sengaja dijauhkan dari `--sudut-merah`
    (#fb7185 vs #d42027) supaya terbaca sebagai saudara titik hijau, bukan
    sebagai sudut — lihat catatan `--g-awas` di `resources/css/dasar.css`.
    Jangan "merapikannya" balik ke `bg-silat-merah`.

    Prop `latensi` mematikan angka milidetiknya. Papan penonton memakainya:
    milidetik adalah angka teknis untuk aparat, dan sebuah papan yang
    dipandangi satu gelanggang penuh tidak punya siapa pun yang bisa
    menindaklanjutinya.
--}}

@props(['latensi' => true])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-live="polite">
    <span x-show="$store.koneksi.tersambung" x-cloak class="flex items-center gap-2">
        @if ($latensi)
            {{--
                Titik memikul warnanya sendirian; angkanya tetap berwarna teks
                netral. Angka berwarna harus lolos ambang teks 4.5, dan
                menaikkan kontrasnya sampai ke sana akan membuat "280 ms"
                lebih menonjol daripada skor pesilat di sebelahnya.
            --}}
            <span class="size-2 shrink-0 rounded-full"
                  :class="{
                      'bg-silat-hidup': $store.koneksi.mutu === null || $store.koneksi.mutu === 'lancar',
                      'bg-silat-emas': $store.koneksi.mutu === 'lambat',
                      'bg-silat-awas': $store.koneksi.mutu === 'buruk',
                  }"></span>

            {{-- Selama belum ada ukuran, kata "tersambung" tetap dipakai apa
                 adanya: belum tahu bukan kabar buruk, dan sebuah "— ms" yang
                 berkedip sesaat tiap panel dibuka hanya melatih petugas
                 mengabaikan penanda ini. --}}
            <span data-latensi class="silat-angka text-[11.5px] text-silat-teks-redup"
                  x-text="$store.koneksi.latensiMs === null ? 'tersambung' : $store.koneksi.latensiMs + ' ms'"></span>
        @else
            <span class="size-2 shrink-0 rounded-full bg-silat-hidup"></span>
            <span class="silat-angka text-[11.5px] text-silat-teks-redup">tersambung</span>
        @endif
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
