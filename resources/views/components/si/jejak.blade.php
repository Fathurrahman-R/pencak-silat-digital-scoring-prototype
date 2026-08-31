@props([
    // ['Label' => url, 'Label terakhir' => null]
    'daftar' => [],
    'akar' => 'Beranda',
])

{{--
    Jejak halaman (breadcrumb).

    Di layar sempit ia DIRINGKAS, bukan disembunyikan. Hierarki aplikasi ini
    dalam -- kejuaraan > kontingen > atlet -- dan panitia lapangan justru yang
    paling sering memakai HP; tanpa jejak mereka kehilangan satu-satunya
    penanda posisi sekaligus satu-satunya jalan naik satu tingkat.

    Yang tampil di HP: induk terdekat, bisa ditekan untuk naik, dan halaman
    sekarang. Sisanya, termasuk akar, muncul begitu ada ruang.
--}}

@php
    $isi = collect($daftar);
    $indukTerdekat = $isi->count() > 1 ? $isi->slice(-2, 1) : collect();

    /*
     * Butir mana yang mengalah kalau jejaknya kepanjangan.
     *
     * Kalau semua butir dibiarkan menyusut, flexbox memotongnya sebanding
     * lebar masing-masing -- "Kejuaraan" ikut jadi "Kejuara…" padahal yang
     * memakan ruang cuma nama kejuaraannya. Yang dipotong satu saja: label
     * terpanjang di antara induk-induknya.
     */
    $labelPanjang = $isi->slice(0, -1)->keys()->sortByDesc(fn ($label) => mb_strlen($label))->first();
@endphp

<nav aria-label="Jejak halaman" {{ $attributes }}>
    {{-- `flex-nowrap` + `overflow-hidden`: jejak yang membungkus ke baris
         kedua menggeser judul halaman ke bawah dan tinggi kepala berubah
         dari satu layar ke layar berikutnya. Nama kejuaraan boleh
         dipotong -- ia tautan, dan halaman yang dituju menyebut namanya
         utuh. --}}
    <ol class="flex min-w-0 flex-nowrap items-center gap-1 overflow-hidden text-[13px] text-ink-muted">
        <li class="hidden shrink-0 sm:block">
            <a href="{{ route('dashboard') }}" class="hover:text-ink">{{ $akar }}</a>
        </li>

        {{-- Penanda "naik satu tingkat" khusus HP. Panah kiri lebih terbaca
             sebagai jalan keluar daripada jejak yang terpotong di tengah. --}}
        @foreach ($indukTerdekat as $label => $url)
            @if ($url)
                <li class="flex shrink-0 items-center gap-1 sm:hidden">
                    <a href="{{ $url }}" class="flex items-center gap-1 hover:text-ink">
                        <x-si.ikon nama="chevron-left" class="size-3.5" />
                        <span class="max-w-[38vw] truncate">{{ $label }}</span>
                    </a>
                    <x-si.ikon nama="chevron-right" class="size-3.5 text-ink-muted" />
                </li>
            @endif
        @endforeach

        @foreach ($daftar as $label => $url)
            {{-- Halaman sekarang tidak ikut menyusut: yang dicari orang di
                 jejak adalah "saya di mana", dan itu butir terakhir. --}}
            <li @class([
                'flex items-center gap-1',
                'min-w-0' => $label === $labelPanjang,
                'shrink-0' => $label !== $labelPanjang,
                // Yang bukan halaman sekarang disembunyikan di HP -- jejak
                // penuhnya sudah diwakili tautan induk di atas.
                'hidden sm:flex' => ! $loop->last,
            ])>
                <x-si.ikon nama="chevron-right" @class(['size-3.5 shrink-0 text-ink-muted', 'hidden sm:block' => $loop->last]) />

                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" @class(['hover:text-ink', 'truncate' => $label === $labelPanjang])>{{ $label }}</a>
                @else
                    <span class="max-w-[46vw] truncate font-medium text-ink sm:max-w-none" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
