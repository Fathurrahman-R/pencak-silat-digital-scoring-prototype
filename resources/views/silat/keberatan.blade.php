<x-layouts.silat :title="'Keberatan — '.$match->bracket->weightClass->name">
    <div x-data="partaiPanel(@js($config))" class="mx-auto flex min-h-screen max-w-4xl flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="silat-angka text-[11px] tracking-[.1em] text-silat-teks-samar">KEBERATAN</p>
                <h1 class="text-[17px] font-medium text-silat-teks">
                    {{ $match->bracket->weightClass->jenis_kelamin->label() }}
                    {{ $match->bracket->weightClass->golongan_usia->label() }} —
                    {{ $match->bracket->weightClass->name }}
                </h1>
            </div>

            <x-silat.indikator-koneksi />
        </header>

        {{-- Galat tidak berbidang merah: merah hanya berarti sudut pesilat. --}}
        <p x-show="galat" x-text="galat" class="rounded-silat bg-silat-garis px-4 py-2 text-[13px] font-medium text-silat-teks"></p>
        <p x-show="pesan" x-text="pesan" class="rounded-silat bg-silat-panel px-4 py-2 text-[13px] text-silat-teks-redup"></p>

        {{--
            Papan skor ikut di sini karena protes tidak pernah soal kejadian yang
            berdiri sendiri — ia soal kejadian pada kedudukan tertentu. Yang
            memutus perlu melihat angka dan hukuman berjalan tanpa berpindah
            layar; sebelumnya panel ini hanya berisi formulir, dan konteksnya
            harus dicari di panel lain sementara tenggat VAR terus berjalan.
        --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <x-silat.papan-skor sudut="red" kunci-skor="merah" />
            <x-silat.papan-skor sudut="blue" kunci-skor="biru" />
        </div>

        <x-silat.blok-keberatan />
    </div>
</x-layouts.silat>
