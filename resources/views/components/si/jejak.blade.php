@props([
    // ['Label' => url, 'Label terakhir' => null]
    'daftar' => [],
    'akar' => 'Dashboard',
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
@endphp

<nav aria-label="Jejak halaman" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1 text-[13px] text-ink-muted">
        <li class="hidden sm:block">
            <a href="{{ route('dashboard') }}" class="truncate hover:text-ink">{{ $akar }}</a>
        </li>

        {{-- Penanda "naik satu tingkat" khusus HP. Panah kiri lebih terbaca
             sebagai jalan keluar daripada jejak yang terpotong di tengah. --}}
        @foreach ($indukTerdekat as $label => $url)
            @if ($url)
                <li class="flex items-center gap-1 sm:hidden">
                    <a href="{{ $url }}" class="flex items-center gap-1 hover:text-ink">
                        <x-si.ikon nama="chevron-left" class="size-3.5" />
                        <span class="max-w-[38vw] truncate">{{ $label }}</span>
                    </a>
                    <x-si.ikon nama="chevron-right" class="size-3.5 text-ink-muted" />
                </li>
            @endif
        @endforeach

        @foreach ($daftar as $label => $url)
            <li @class([
                'flex items-center gap-1',
                // Yang bukan halaman sekarang disembunyikan di HP -- jejak
                // penuhnya sudah diwakili tautan induk di atas.
                'hidden sm:flex' => ! $loop->last,
            ])>
                <x-si.ikon nama="chevron-right" @class(['size-3.5 text-ink-muted', 'hidden sm:block' => $loop->last]) />

                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-ink">{{ $label }}</a>
                @else
                    <span class="max-w-[46vw] truncate font-medium text-ink sm:max-w-none" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
