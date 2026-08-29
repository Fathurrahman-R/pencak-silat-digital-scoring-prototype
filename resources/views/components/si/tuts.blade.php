{{--
    Satu tuts papan ketik yang disebut di dalam teks atau menu.

    Bentuknya tetap satu, tidak bergantung permukaan tempatnya duduk: dua
    varian yang berbeda arah cahayanya membuat dua tuts yang sama terbaca
    seperti dua benda berbeda.
--}}

<kbd {{ $attributes->class(
    'inline-flex items-center rounded-[var(--radius-kecil)] border border-line-strong bg-surface-inset px-1.5 py-px font-mono text-[12px] leading-[1.6] whitespace-nowrap text-ink-secondary',
) }}>{{ $slot }}</kbd>
