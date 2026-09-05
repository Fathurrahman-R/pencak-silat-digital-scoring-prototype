<x-layouts.silat :title="'Ketua Pertandingan — '.$arena->name" :manifest="$manifestUrl ?? null">
    {{--
        Panel Ketua Pertandingan untuk SATU gelanggang.

        Naskah tidak pernah menuliskan "satu gelanggang satu Ketua
        Pertandingan", tapi kehadirannya di gelanggang tertulis di mana-mana:
        pesilat memberi hormat kepadanya, ia memanggil Wasit dengan bel saat
        pesilat cedera, ia menghentikan penampilan Jurus yang gagal, dan ia
        bertanggung jawab atas waktu penampilan Kategori Jurus. Satu orang
        tidak bisa duduk di dua gelanggang sekaligus.

        Karena itu panelnya ikut gelanggang seperti peran lain, dan berpindah
        sendiri saat pengendali mengganti partai. Ringkasan LINTAS gelanggang
        tetap ada sebagai halaman terpisah — tugas kejuaraannya (memimpin Rapat
        Teknik, mengganti Petugas Teknis, memutus protes manajer tingkat
        pertama) memang selebar itu, dan membuang layarnya berarti menghapus
        satu-satunya tempat seluruh kejuaraan terbaca saat berjalan.
    --}}
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-dvh max-w-4xl flex-col gap-4 p-4">

        <x-silat.pita-susulan />

        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="silat-angka text-[10.5px] tracking-[.12em] text-silat-teks-samar uppercase">
                    Ketua Pertandingan ·
                    <span x-text="(identitas.gelanggang ?? '{{ $arena->name }}') + ' · Partai ' + (identitas.partai ?? '—')"></span>
                </p>
                <h1 class="mt-1 text-[19px] leading-[1.3] font-semibold tracking-[-0.02em] text-silat-teks"
                    x-text="[identitas.jenis_kelamin, identitas.golongan, identitas.kelas].filter(Boolean).join(' · ')"></h1>
                <p class="mt-0.5 text-[13px] text-silat-teks-redup" x-text="identitas.babak_bagan"></p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-2">
                <x-silat.indikator-koneksi />

                {{--
                    Ringkasan lintas gelanggang tidak dibuang, hanya dipindah
                    satu ketukan. Pengalihan ke panel gelanggang tidak boleh
                    mengunci siapa pun di satu layar.
                --}}
                <a href="{{ route('admin.turnamen.ketua-pertandingan.index', $tournament) }}"
                   class="text-[12.5px] text-silat-teks-kedua underline underline-offset-2">
                    Seluruh gelanggang
                </a>
            </div>
        </header>

        {{-- Galat tidak berbidang merah: merah hanya berarti sudut pesilat. --}}
        <p x-show="galat" x-text="galat" class="rounded-silat bg-silat-garis px-4 py-2 text-[13px] font-medium text-silat-teks"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        {{--
            Papan skor berjalan. Yang memutus protes perlu melihat kedudukan
            saat kejadiannya disengketakan, bukan mencarinya di panel lain
            sementara tenggatnya berjalan.
        --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" />
        </div>

        <x-silat.blok-keberatan />

        {{-- Sesudah partai selesai, rinciannya terbaca di tempat yang sama. --}}
        <template x-if="sudahSelesai">
            <div class="rounded-silat-besar border border-silat-garis p-4.5">
                <x-silat.papan-hasil />
            </div>
        </template>
    </div>
</x-layouts.silat>
