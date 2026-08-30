{{--
    Penomoran halaman.

    Bawaan Laravel memakai kelas warna mentah (bg-white, text-gray-700, dan
    varian dark:) yang tidak ikut berganti saat data-theme berubah. Versi ini
    memakai token, jadi ia satu bahasa dengan sisa aplikasi.

    Tombolnya 30px persegi dari material yang bisa ditekan, halaman aktif
    memakai aksen — mengikuti seksi "Tampilan Data" di design system.
--}}

@php
    $tombol = 'inline-flex h-[30px] min-w-[30px] items-center justify-center rounded-sm border border-line-strong bg-[image:var(--mat-raised)] px-2 text-[13px] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:translate-y-px active:shadow-press';
    $mati = 'inline-flex h-[30px] min-w-[30px] cursor-not-allowed items-center justify-center rounded-sm border border-line bg-surface-sunken px-2 text-[13px] text-ink-muted shadow-well';
    $aktif = 'inline-flex h-[30px] min-w-[30px] items-center justify-center rounded-sm border border-transparent bg-[image:var(--mat-accent)] px-2 text-[13px] font-semibold text-accent-on shadow-lift';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-muted">
            Menampilkan
            <span class="num">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="num">{{ $paginator->lastItem() ?? 0 }}</span>
            dari <span class="num">{{ $paginator->total() }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $mati }}" aria-disabled="true">
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                    <x-si.ikon nama="chevron-left" class="size-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $tombol }}">
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                    <x-si.ikon nama="chevron-left" class="size-4" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $mati }}" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $aktif }}" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $tombol }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $tombol }}">
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                    <x-si.ikon nama="chevron-right" class="size-4" />
                </a>
            @else
                <span class="{{ $mati }}" aria-disabled="true">
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                    <x-si.ikon nama="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@else
    {{-- Satu halaman saja: jumlahnya tetap disebut, tombolnya tidak perlu. --}}
    <p class="text-[13px] text-ink-muted">
        <span class="num">{{ $paginator->total() }}</span> baris
    </p>
@endif
