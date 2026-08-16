<x-layouts.silat :title="'Juri — '.$match->bracket->weightClass->name">
    @push('head')
        <link rel="manifest" href="{{ $manifestUrl }}">
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
        <header class="flex shrink-0 items-center justify-between gap-3 px-3 py-2">
            <p class="silat-angka text-[11px] text-silat-teks-redup">
                Babak <span x-text="match.current_round ?? '–'"></span>
                <span x-show="babakAktif?.status !== 'berjalan'" class="text-silat-teks-samar">· menunggu wasit</span>
            </p>

            <span
                class="rounded-full px-2.5 py-1 text-[11px] tracking-wide"
                x-bind:class="$store.koneksi.tersambung ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'"
                x-text="$store.koneksi.tersambung ? 'Tersambung' : 'Terputus'"
            ></span>
        </header>

        <p x-show="galat" x-text="galat" x-cloak
           class="mx-3 shrink-0 rounded-silat bg-red-500/15 px-3 py-1.5 text-center text-[12px] text-red-300"></p>
        <p x-show="pesan && ! galat" x-text="pesan" x-cloak
           class="mx-3 shrink-0 rounded-silat bg-silat-panel px-3 py-1.5 text-center text-[12px] text-silat-teks-redup"></p>

        <div class="grid min-h-0 flex-1 grid-cols-2 gap-2 p-2">
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
