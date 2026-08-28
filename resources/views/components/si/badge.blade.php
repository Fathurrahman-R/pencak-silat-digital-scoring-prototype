@props([
    'varian' => 'netral',
    'ikon' => null,
])

@php
    /*
     * Badge SELALU membawa kata, dan sedapat mungkin ikon. Warna tidak pernah
     * jadi satu-satunya pembawa makna -- panitia yang sulit membedakan warna
     * harus tetap bisa membaca status pendaftaran.
     */
    $varianKelas = [
        'netral' => 'bg-surface-inset text-ink-secondary',
        'sukses' => 'bg-success-soft text-success',
        'perhatian' => 'bg-warning-soft text-warning',
        'bahaya' => 'bg-danger-soft text-danger',
    ];

    $ikonBawaan = [
        'sukses' => 'check',
        'perhatian' => 'clock',
        'bahaya' => 'x',
    ];

    $ikonDipakai = $ikon ?? ($ikonBawaan[$varian] ?? null);
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-[var(--radius-kecil)] px-2 py-1 text-[13px] font-semibold '
        .($varianKelas[$varian] ?? $varianKelas['netral']),
]) }}>
    @if ($ikonDipakai)
        <x-si.ikon :nama="$ikonDipakai" class="size-3.5 shrink-0" />
    @endif
    {{ $slot }}
</span>
