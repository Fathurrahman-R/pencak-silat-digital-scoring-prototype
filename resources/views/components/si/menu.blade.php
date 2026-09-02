@props([
    'id' => null,
    'label' => null,
    'letak' => 'bawah',
    'lebar' => 'w-56',
])

{{--
    Menu turun.

    Bertingkat lebih dari satu tidak dipakai: kalau butuh submenu, yang
    dibutuhkan sebenarnya halaman tersendiri. Menu bertingkat menuntut tangan
    yang mantap di layar sentuh, dan panitia memakainya sambil berdiri.

    Esc menutup DAN mengembalikan fokus ke pemicunya. Tanpa itu, fokus
    tertinggal di menu yang sudah tidak ada dan Tab berikutnya melompat ke
    tempat yang tak terduga.
--}}

@php
    $id ??= 'menu-'.Str::random(8);

    $arah = [
        'bawah' => 'top-full mt-1.5 start-0',
        'bawah-kanan' => 'top-full mt-1.5 end-0',
        'atas' => 'bottom-full mb-1.5 start-0',
        'atas-kanan' => 'bottom-full mb-1.5 end-0',
    ];
@endphp

<div x-data="{ buka: false }"
     x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu?.focus() }"
     x-on:click.outside="buka = false"
     class="relative inline-block">

    @isset($pemicu)
        {{-- Pemicu buatan sendiri: pemanggilnya yang menentukan rupanya. --}}
        <button type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                :aria-expanded="buka" aria-haspopup="menu" aria-controls="{{ $id }}"
                class="flex cursor-pointer items-center rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ink/10">
            {{ $pemicu }}
        </button>
    @else
        <button type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                :aria-expanded="buka" aria-haspopup="menu" aria-controls="{{ $id }}"
                {{ $attributes->class('inline-flex h-9 items-center gap-2 rounded-[var(--radius)] border border-line-strong bg-surface-raised px-3.5 text-[13.5px] font-medium text-ink outline-none focus-visible:ring-[3px] focus-visible:ring-ink/10') }}>
            {{ $label }}
            <x-si.ikon nama="chevron-down" class="size-4" />
        </button>
    @endisset

    <div id="{{ $id }}" role="menu" x-show="buka" x-cloak
         class="absolute z-50 {{ $arah[$letak] ?? $arah['bawah'] }} {{ $lebar }} rounded-[var(--radius)] border border-line bg-surface-raised p-1.5">
        <ul class="text-[14px] text-ink">
            {{ $slot }}
        </ul>
    </div>
</div>
