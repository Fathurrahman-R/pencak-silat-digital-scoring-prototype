@props([
    'title' => null,
    'heading' => null,
    'description' => null,
])

<x-layouts.base :title="$title ?? $heading">
    <div class="bg-glow relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="relative mb-6 flex items-center gap-2.5">
            <span class="flex size-8 items-center justify-center rounded-md bg-accent font-display text-[15px] font-bold text-accent-on">
                {{ mb_substr(config('app.name'), 0, 1) }}
            </span>
            <span class="font-display text-lg font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
        </a>

        <div class="relative w-full max-w-md rounded-xl border border-line bg-surface-raised p-6 shadow-md sm:p-8">
            @if ($heading)
                <h1 class="font-display text-[22px] font-semibold text-ink">{{ $heading }}</h1>
            @endif

            @if ($description)
                <p class="mt-2 text-sm text-ink-secondary">{{ $description }}</p>
            @endif

            <div class="mt-6 space-y-4">
                {{ $slot }}
            </div>
        </div>

        <button type="button" data-theme-toggle
                class="relative mt-6 inline-flex size-9 items-center justify-center rounded-md border border-line-strong bg-[image:var(--mat-raised)] text-ink-secondary shadow-[var(--bevel),var(--lift)] transition hover:brightness-95 active:translate-y-px active:shadow-press focus-visible:ring-3 focus-visible:ring-accent-soft focus-visible:outline-none">
            <span class="sr-only">Ganti tema</span>
            <x-si.ikon nama="sun-moon" class="size-4" />
        </button>
    </div>
</x-layouts.base>
