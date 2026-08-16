@props([
    'label',
    'value',
    'delta' => null,
    'trend' => null,
    'icon' => null,
])

{{--
    Kartu metrik: label mono huruf besar, angka besar dengan digit tabular,
    lalu perubahannya. Permukaannya selalu solid — kaca hanya dipakai sidebar
    dan topbar, dan angka lebih sulit dibaca di atasnya.

    $trend: 'up' | 'down' | 'flat'. Warna hanya dipakai kalau arahnya memang
    punya arti; 'flat' sengaja abu-abu.
--}}

@php
    $trends = [
        'up' => ['icon' => 'trending-up', 'fg' => 'text-success'],
        'down' => ['icon' => 'trending-down', 'fg' => 'text-danger'],
        'flat' => ['icon' => 'minus', 'fg' => 'text-ink-muted'],
    ];

    $style = $trends[$trend] ?? null;
@endphp

<div {{ $attributes->class('rounded-lg border border-line bg-surface-raised p-[13px] shadow-lift') }}>
    <div class="flex items-start justify-between gap-3">
        <span class="eyebrow">{{ $label }}</span>

        @if ($icon)
            <x-ui.icon :name="$icon" class="size-4 text-ink-muted" />
        @endif
    </div>

    <div class="mt-[5px] font-display text-[22px] leading-tight font-semibold tabular-nums text-ink">{{ $value }}</div>

    @if ($delta)
        <div class="mt-[3px] flex items-center gap-1.5 text-sm2 {{ $style['fg'] ?? 'text-ink-muted' }}">
            @if ($style)
                <x-ui.icon :name="$style['icon']" class="size-3.5" />
            @endif
            {{ $delta }}
        </div>
    @endif
</div>
