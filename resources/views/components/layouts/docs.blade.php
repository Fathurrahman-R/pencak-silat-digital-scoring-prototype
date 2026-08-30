@props([
    'title' => null,
    'nav' => [],
])

{{--
    Layout dokumentasi: topbar + daftar isi yang menempel. Sengaja bukan shell
    admin, supaya halaman ini bisa dibuka tanpa login dan tidak tercampur
    dengan menu aplikasi.

    $nav berbentuk ['#anchor' => 'Label'] atau ['Kelompok' => ['#a' => 'Label']].
--}}

@php
    /*
     * Dua halaman peraga yang tersisa setelah lapisan komponen lama dibongkar.
     * Keduanya sengaja terpisah: `si` memakai bundel admin (terang), `gelanggang`
     * memakai bundel silat (gelap) dan tidak memuat app.css sama sekali —
     * sehingga token yang bocor antar-bundel langsung kelihatan.
     */
    $halaman = [
        'si' => 'Komponen si/*',
        'gelanggang' => 'Gelanggang',
    ];
@endphp

<x-layouts.base :title="$title ? $title.' — Design system' : 'Design system'">
    <header class="glass sticky top-0 z-40 rounded-none border-x-0 border-t-0">
        <div class="mx-auto flex h-[62px] max-w-[1240px] items-center gap-4 px-6">
            <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2.5">
                <span class="flex size-[26px] items-center justify-center rounded-sm bg-accent font-display text-sm font-bold text-accent-on">
                    {{ mb_substr(config('app.name'), 0, 1) }}
                </span>
                <span class="font-display text-base font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
                <span class="hidden rounded-full border border-line px-2 py-px font-mono text-[11px] text-ink-muted sm:inline">
                    peraga komponen
                </span>
            </a>

            <nav class="ms-4 hidden gap-1 md:flex">
                @foreach ($halaman as $route => $label)
                    <a href="{{ route('design-system.'.$route) }}"
                       @class([
                           'rounded-sm px-2.5 py-1.5 text-[13.5px] transition',
                           'bg-surface-inset font-semibold text-ink' => request()->routeIs('design-system.'.$route),
                           'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! request()->routeIs('design-system.'.$route),
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="flex-1"></div>

            <a href="{{ Auth::check() ? route('dashboard') : route('login') }}"
               class="hidden text-[13.5px] text-ink-muted transition hover:text-ink sm:inline">
                {{ Auth::check() ? 'Ke aplikasi' : 'Masuk' }}
            </a>

            <button type="button" data-theme-toggle
                    class="inline-flex size-9 items-center justify-center rounded-md text-ink-secondary transition hover:bg-surface-inset hover:text-ink focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-accent-soft">
                <span class="sr-only">Ganti tema</span>
                <x-si.ikon nama="sun-moon" class="size-5" />
            </button>
        </div>
    </header>

    <div class="mx-auto grid max-w-[1240px] items-start gap-14 px-6 lg:grid-cols-[196px_minmax(0,1fr)]">
        <nav class="sticky top-[62px] hidden flex-col gap-px py-10 text-[13.5px] lg:flex">
            @foreach ($nav as $key => $value)
                @if (is_array($value))
                    <span class="eyebrow px-2.5 pt-4 pb-2 first:pt-0">{{ $key }}</span>

                    @foreach ($value as $anchor => $label)
                        <a href="{{ $anchor }}" class="rounded-sm px-2.5 py-1.5 text-ink-secondary transition hover:bg-surface-inset hover:text-ink">{{ $label }}</a>
                    @endforeach
                @else
                    <a href="{{ $key }}" class="rounded-sm px-2.5 py-1.5 text-ink-secondary transition hover:bg-surface-inset hover:text-ink">{{ $value }}</a>
                @endif
            @endforeach
        </nav>

        <main class="min-w-0 py-10 pb-28">
            {{ $slot }}

            <footer class="mt-24 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-7 text-[13px] text-ink-muted">
                <span>Dirender dari komponen yang sama dengan yang dipakai aplikasi.</span>
                <span class="font-mono text-xs">document/design-system · prototipe asal</span>
            </footer>
        </main>
    </div>
</x-layouts.base>
