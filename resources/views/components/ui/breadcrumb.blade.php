@props([
    'items' => [],
    'root' => 'Dashboard',
])

{{-- $items berbentuk ['Label' => url, 'Label terakhir' => null] --}}

@php
    /*
     * Di layar sempit breadcrumb tidak disembunyikan, tapi diringkas.
     *
     * Sebelumnya seluruh jejaknya hilang di bawah 640px. Hierarki aplikasi ini
     * dalam — turnamen > kontingen > atlet — dan panitia lapangan justru yang
     * paling sering memakai HP; tanpa breadcrumb mereka kehilangan satu-satunya
     * penanda posisi sekaligus satu-satunya jalan naik satu tingkat.
     *
     * Yang ditampilkan di HP: induk terdekat (bisa diklik untuk naik) dan
     * halaman sekarang. Sisanya, termasuk akar, hanya muncul begitu ada ruang.
     */
    $daftar = collect($items);
    $indukTerdekat = $daftar->count() > 1 ? $daftar->slice(-2, 1) : collect();
@endphp

<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1 text-[13px] text-ink-muted">
        <li class="hidden sm:block">
            <a href="{{ route('dashboard') }}" class="truncate transition hover:text-ink">{{ $root }}</a>
        </li>

        {{-- Penanda "naik satu tingkat" khusus HP: ikon panah kiri lebih terbaca
             sebagai jalan keluar daripada potongan jejak yang terpotong. --}}
        @foreach ($indukTerdekat as $label => $url)
            @if ($url)
                <li class="flex items-center gap-1 sm:hidden">
                    <a href="{{ $url }}" class="flex items-center gap-1 transition hover:text-ink">
                        <x-ui.icon name="chevron-left" class="size-3.5" />
                        <span class="max-w-[38vw] truncate">{{ $label }}</span>
                    </a>
                    <x-ui.icon name="chevron-right" class="size-3.5 text-ink-muted" />
                </li>
            @endif
        @endforeach

        @foreach ($items as $label => $url)
            <li @class([
                'flex items-center gap-1',
                // Yang bukan halaman sekarang disembunyikan di HP -- jejak
                // penuhnya sudah diwakili tautan induk di atas.
                'hidden sm:flex' => ! $loop->last,
            ])>
                <x-ui.icon name="chevron-right" @class(['size-3.5 text-ink-muted', 'hidden sm:block' => $loop->last]) />

                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="transition hover:text-ink">{{ $label }}</a>
                @else
                    <span class="max-w-[46vw] truncate font-medium text-ink sm:max-w-none" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
