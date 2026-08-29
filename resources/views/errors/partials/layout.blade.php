@php
    $hint ??= null;
@endphp

<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
        <p class="text-6xl font-bold text-line-strong">{{ $code }}</p>

        <h1 class="text-2xl font-semibold text-ink">{{ $title }}</h1>

        <p class="max-w-md text-ink-muted">{{ $message }}</p>

        @if ($hint)
            <p class="max-w-md text-sm text-ink-muted">{{ $hint }}</p>
        @endif

        <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
            <x-si.tombol :tautan="url()->previous()" varian="kedua">Kembali</x-si.tombol>

            @auth
                <x-si.tombol :tautan="route('dashboard')">Ke dashboard</x-si.tombol>
            @else
                <x-si.tombol :tautan="url('/')">Ke beranda</x-si.tombol>
            @endauth
        </div>
    </div>
</x-layouts.base>
