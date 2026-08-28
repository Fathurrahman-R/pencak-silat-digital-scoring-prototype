<x-layouts.silat :title="'Juri — '.$match->bracket->weightClass->name">
    @push('head')
        {{--
            `crossorigin="use-credentials"` bukan hiasan. Manifest diambil peramban
            tanpa kredensial secara bawaan, jadi tanpa atribut ini permintaannya
            masuk sebagai tamu, kena redirect ke /login, dan yang diterima adalah
            HTML — peramban menolaknya dengan "Manifest: Line: 1, column: 1,
            Syntax error" dan panel juri tidak pernah bisa dipasang sebagai PWA.
            Rutenya berada di balik auth, jadi manifest ini memang wajib bercookie.
        --}}
        <link rel="manifest" href="{{ $manifestUrl }}" crossorigin="use-credentials">
        <link rel="icon" href="/icons/juri.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/juri.svg">
    @endpush

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
        <header class="grid shrink-0 grid-cols-[1fr_auto_1fr] items-center gap-4 px-3 py-1.5">
            <div class="flex min-w-0 items-center gap-2">
                <span class="size-2.5 shrink-0 rounded-full bg-silat-merah"></span>
                <span class="truncate text-[14px] font-medium text-silat-teks"
                      x-text="match.red?.athletes?.join(', ') ?? '—'"></span>
            </div>

            <div class="flex items-center gap-3">
                <p class="silat-angka text-[11px] whitespace-nowrap text-silat-teks-redup">
                    Babak <span x-text="match.current_round ?? '–'"></span>
                    <span x-show="babakAktif?.status !== 'berjalan'">· menunggu wasit</span>
                </p>
                <x-silat.indikator-koneksi />
            </div>

            <div class="flex min-w-0 items-center justify-end gap-2">
                <span class="truncate text-[14px] font-medium text-silat-teks"
                      x-text="match.blue?.athletes?.join(', ') ?? '—'"></span>
                <span class="size-2.5 shrink-0 rounded-full bg-silat-biru"></span>
            </div>
        </header>

        <p x-show="galat" x-text="galat" x-cloak
           class="mx-3 shrink-0 rounded-silat bg-red-500/15 px-3 py-1.5 text-center text-[12px] text-red-300"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="mx-3 shrink-0 rounded-silat bg-silat-panel px-3 py-1.5 text-center text-[12px] text-silat-teks-redup"></p>

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
        <div class="grid min-h-0 flex-1 grid-cols-2 gap-x-6 gap-y-2 p-2">
            <div class="grid grid-rows-3 gap-2">
                @foreach (['pukulan', 'tendangan', 'jatuhan'] as $jenis)
                    <x-silat.tombol-nilai :jenis="$jenis" sudut="merah" x-on:click="kirimNilai('red', '{{ $jenis }}')" class="h-full" />
                @endforeach
            </div>

            <div class="grid grid-rows-3 gap-2">
                @foreach (['pukulan', 'tendangan', 'jatuhan'] as $jenis)
                    <x-silat.tombol-nilai :jenis="$jenis" sudut="biru" x-on:click="kirimNilai('blue', '{{ $jenis }}')" class="h-full" />
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.silat>
