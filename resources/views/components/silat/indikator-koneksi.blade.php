{{--
    Penanda sambungan gelanggang, dipakai seluruh panel dan halaman live.

    Kedua keadaan sengaja TIDAK setara bobotnya. Tersambung adalah keadaan
    normal dan tidak perlu menarik perhatian, jadi ia tetap chip kecil. Terputus
    adalah keadaan yang menghentikan pekerjaan — nilai yang ditekan tidak sampai
    ke server — jadi ia tampil lebih besar, berkedip pelan, dan menyebutkan apa
    yang harus dilakukan. Sebelumnya keduanya berupa chip 11px yang sama, dan
    satu-satunya beda hanya warnanya.

    Kontras kedua keadaan sudah diperiksa terhadap latar panggung (#0b0b0c),
    bukan terhadap warna dasar tint-nya: emerald-300 di atas emerald-500/15
    mencapai 10.6:1, red-200 di atas red-500/20 mencapai 9.8:1.
--}}

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-live="polite">
    <span x-show="$store.koneksi.tersambung" x-cloak
          class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] tracking-wide text-emerald-300">
        Tersambung
    </span>

    <span x-show="! $store.koneksi.tersambung" x-cloak
          class="flex items-center gap-2 rounded-silat bg-red-500/20 px-3 py-2 text-red-200 motion-safe:animate-pulse">
        <span class="text-[14px] font-medium">Terputus</span>
        <span class="hidden text-[12px] text-red-300/90 sm:inline">
            nilai belum tersimpan — periksa jaringan gelanggang
        </span>
    </span>
</div>
