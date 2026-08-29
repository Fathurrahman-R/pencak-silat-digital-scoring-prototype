@props([
    'tautan' => null,
    'bahaya' => false,
    'pintasan' => null,
])

{{--
    Satu butir menu. Tingginya 40px penuh, bukan sekadar setinggi hurufnya:
    butir menu yang rapat mudah tertekan salah satu, dan salah satunya bisa
    "Keluar".
--}}

@php
    $kelas = implode(' ', [
        'flex min-h-10 w-full items-center gap-2.5 rounded-[var(--radius-kecil)] px-2.5 py-2 text-left text-[14px]',
        '[&>svg]:size-4 [&>svg]:shrink-0',
        'focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none',
        $bahaya
            ? 'text-danger hover:bg-danger-soft [&>svg]:text-danger'
            : 'text-ink hover:bg-surface-inset [&>svg]:text-ink-muted',
    ]);
@endphp

<li role="none">
    @if ($tautan)
        <a href="{{ $tautan }}" role="menuitem" {{ $attributes->class($kelas) }}>
            {{ $slot }}
            @if ($pintasan)
                <span class="ms-auto font-mono text-[12px] text-ink-muted">{{ $pintasan }}</span>
            @endif
        </a>
    @else
        <button type="button" role="menuitem" {{ $attributes->class($kelas) }}>
            {{ $slot }}
            @if ($pintasan)
                <span class="ms-auto font-mono text-[12px] text-ink-muted">{{ $pintasan }}</span>
            @endif
        </button>
    @endif
</li>
