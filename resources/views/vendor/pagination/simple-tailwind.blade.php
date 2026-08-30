{{-- Versi tanpa nomor halaman, untuk daftar yang tidak dihitung totalnya. --}}

@php
    $tombol = 'inline-flex h-[30px] items-center gap-1.5 rounded-sm border border-line-strong bg-surface-raised px-3 text-[13px] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:translate-y-px active:shadow-press';
    $mati = 'inline-flex h-[30px] cursor-not-allowed items-center gap-1.5 rounded-sm border border-line bg-surface-sunken px-3 text-[13px] text-ink-muted shadow-well';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="{{ $mati }}" aria-disabled="true">
                <x-si.ikon nama="chevron-left" class="size-4" />
                {{ __('pagination.previous') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $tombol }}">
                <x-si.ikon nama="chevron-left" class="size-4" />
                {{ __('pagination.previous') }}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $tombol }}">
                {{ __('pagination.next') }}
                <x-si.ikon nama="chevron-right" class="size-4" />
            </a>
        @else
            <span class="{{ $mati }}" aria-disabled="true">
                {{ __('pagination.next') }}
                <x-si.ikon nama="chevron-right" class="size-4" />
            </span>
        @endif
    </nav>
@endif
