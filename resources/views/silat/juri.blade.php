<x-layouts.silat :title="'Juri — '.$match->bracket->weightClass->name" :manifest="$manifestUrl ?? null">
    {{--
        x-data hanya memanggil partaiPanel(cfg) langsung, TIDAK disebar lewat
        {...partaiPanel(cfg), ...tambahan}. Penyebaran objek mengevaluasi
        getter (babakAktif, sudahSelesai, dst.) sekali saat itu juga dan
        membekukan hasilnya jadi nilai statis, bukan menyalin definisi
        getter-nya -- ditemukan langsung lewat pengujian manual: label babak
        macet permanen di "menunggu wasit" walau server sudah menandai babak
        berjalan. Wake lock dan pendaftaran service worker karena itu hidup
        di factory partaiPanel sendiri (silat.js), dipanggil otomatis dari
        init()-nya untuk keempat panel sekaligus, bukan ditambal di sini.
    --}}
    <div
        x-data="partaiPanel(@js($config))"
        x-init="if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js')"
        class="flex h-dvh flex-col overflow-hidden select-none"
    >
        {{--
            Kepala dibuat setipis mungkin: aparat memegang HP dalam orientasi
            landscape, dan di sana tinggi adalah barang langka -- setiap piksel
            yang dipakai kepala diambil dari tombol nilai.

            Nama pesilat ikut tampil, merah kiri dan biru kanan mengikuti sisi
            tombolnya. Tanpa itu juri harus mengingat sendiri siapa yang berdiri
            di sudut mana, dan kolom yang ditekannya hanya berlabel warna.
        --}}
        {{--
            Verifikasi MENGGANTIKAN seluruh isi panel, bukan menutupinya.

            <template x-if> membongkar tombol nilai dari DOM, sedangkan x-show
            hanya menyembunyikannya -- dan tombol yang cuma tersembunyi masih
            bisa tertekan lewat celah render, fokus keyboard, atau kesalahan
            urutan lapisan. Juri yang sedang diminta menjawab tidak boleh bisa
            memberi nilai untuk kejadian yang justru sedang dipertanyakan.
        --}}
        <template x-if="verifikasiBerjalan">
            <x-silat.verifikasi-juri />
        </template>

        <template x-if="! verifikasiBerjalan">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">

        {{-- Bagian yang paling menentukan apakah fitur ini aman: juri yang
             tidak menyadari panelnya sedang mencatat babak lama akan menekan
             nilai untuk kejadian di depan matanya, dan nilai itu masuk ke babak
             yang sudah selesai -- kekeliruan tanpa jalan koreksi murah. --}}
        <x-silat.pita-susulan />

        <header class="grid shrink-0 grid-cols-[1fr_auto_1fr] items-center gap-4 px-3 py-1.5">
            <div class="flex min-w-0 items-center gap-2">
                <span class="size-2.5 shrink-0 rounded-full bg-silat-merah"></span>
                <span class="truncate text-[14px] font-medium text-silat-teks"
                      x-text="match.red?.athletes?.join(', ') ?? '—'"></span>
            </div>

            <div class="flex items-center gap-3">
                {{--
                    Selama susulan, keadaan babak berjalan DISEMBUNYIKAN
                    seluruhnya, bukan ditampilkan diam. Keterangan yang diam
                    mudah disangka panel macet; keterangan yang hilang tidak.
                --}}
                <p class="silat-angka text-[11px] whitespace-nowrap text-silat-teks-redup"
                   x-show="! susulanTerbuka">
                    Babak <span x-text="match.current_round ?? '–'"></span>
                    <span x-show="babakAktif?.status !== 'berjalan'">· menunggu wasit</span>
                </p>
                <p class="silat-angka text-[11px] whitespace-nowrap text-amber-300"
                   x-show="susulanTerbuka" x-cloak>
                    Babak berjalan dijeda
                </p>
                <x-silat.indikator-koneksi />
            </div>

            <div class="flex min-w-0 items-center justify-end gap-2">
                <span class="truncate text-[14px] font-medium text-silat-teks"
                      x-text="match.blue?.athletes?.join(', ') ?? '—'"></span>
                <span class="size-2.5 shrink-0 rounded-full bg-silat-biru"></span>
            </div>
        </header>

        {{--
            Galat tidak berbidang merah. Merah hanya berarti sudut pesilat
            (BRIEF §2.2), dan sebuah pesan merah di panel yang tombolnya juga
            merah adalah cara tercepat membuat juri salah baca. Bedanya dengan
            pesan biasa dibawa terang teks, bukan warna.
        --}}
        <p x-show="galat" x-text="galat" x-cloak
           class="mx-3 shrink-0 rounded-silat-kecil bg-silat-garis px-2.5 py-1.5 text-center text-[12px] font-medium text-silat-teks"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="mx-3 shrink-0 rounded-silat-kecil bg-silat-garis px-2.5 py-1.5 text-center text-[12px] text-silat-teks-kedua"></p>

        {{--
            Jarak mendatar dan menegak SENGAJA tidak sama, dan itu bukan soal rupa.
            Selip jempol ke atas atau ke bawah hanya menggeser jenis serangan pada
            pesilat yang benar — salah 1 nilai, ketahuan, bisa dibatalkan dewan juri.
            Selip ke samping memberikan nilai kepada LAWAN, kesalahan yang paling
            mahal di seluruh sistem ini dan paling sulit disadari saat terjadi.

            Karena itu lorong tengah jauh lebih lebar daripada sela antar baris.
            Sebelumnya keduanya `gap-2` (8px) — dua kesalahan dengan biaya sangat
            berbeda dibuat sama-sama mudah dilakukan.
        --}}
        <div class="grid min-h-0 flex-1 grid-cols-2 gap-x-[var(--silat-lorong-sudut)] gap-y-2 px-3 pt-2 pb-3">
            {{-- Dua jenis saja. Jatuhan tidak dinilai juri: nilainya mutlak,
                 keputusan Dewan Wasit Juri, dan panelnya yang menerbitkan. --}}
            <div class="grid grid-rows-2 gap-2">
                @foreach (['pukulan', 'tendangan'] as $jenis)
                    <x-silat.tombol-nilai :jenis="$jenis" sudut="merah" x-on:click="kirimNilai('red', '{{ $jenis }}')" class="h-full" />
                @endforeach
            </div>

            <div class="grid grid-rows-2 gap-2">
                @foreach (['pukulan', 'tendangan'] as $jenis)
                    <x-silat.tombol-nilai :jenis="$jenis" sudut="biru" x-on:click="kirimNilai('blue', '{{ $jenis }}')" class="h-full" />
                @endforeach
            </div>
        </div>
            </div>
        </template>
    </div>
</x-layouts.silat>
