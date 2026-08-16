@props([
    'title' => 'Belum ada data',
    'description' => null,
    'icon' => 'inbox',
])

{{-- Layar kosong adalah ajakan bertindak, bukan permintaan maaf. --}}

<div {{ $attributes->class('flex flex-col items-center justify-center gap-1 px-6 py-8 text-center') }}>
    <div class="mb-1 flex size-9 items-center justify-center rounded-lg bg-surface-sunken text-ink-muted shadow-well">
        <x-ui.icon :name="$icon" class="size-[18px]" />
    </div>

    <p class="font-display text-base font-semibold text-ink">{{ $title }}</p>

    @if ($description)
        <p class="max-w-[52ch] text-base2 text-ink-secondary">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
