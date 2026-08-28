@props([
    'varian' => 'keterangan',
    'judul' => null,
])

@php
    /*
     * Callout dipakai untuk menyatakan PRASYARAT dan AKIBAT -- kenapa sebuah
     * tombol belum bisa ditekan, apa yang harus diselesaikan lebih dulu.
     * Tombol nonaktif tanpa penjelasan sama membingungkannya dengan tombol
     * yang gagal diam-diam.
     */
    $varianKelas = [
        'keterangan' => 'border-line bg-surface-inset text-ink-secondary',
        'perhatian' => 'border-warning bg-warning-soft text-warning',
        'bahaya' => 'border-danger bg-danger-soft text-danger',
    ];
@endphp

<div {{ $attributes->merge([
    'class' => 'rounded-[var(--radius)] border-l-[3px] px-4 py-3 '
        .($varianKelas[$varian] ?? $varianKelas['keterangan']),
]) }}>
    @if ($judul)
        <p class="text-[15px] font-semibold">{{ $judul }}</p>
    @endif
    <div @class(['text-[14px] leading-relaxed', 'mt-1' => $judul])>{{ $slot }}</div>
</div>
