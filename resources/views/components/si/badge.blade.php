@props([
    'varian' => 'netral',
    'ikon' => null,
])

@php
    /*
     * Badge — DESIGN-SYSTEM.md §8.
     *
     * SELALU membawa kata, dan sedapat mungkin ikon. Warna tidak pernah jadi
     * satu-satunya pembawa makna: panitia yang sulit membedakan warna harus
     * tetap bisa membaca status pendaftaran.
     *
     * `kosong` adalah varian putus-putus untuk "belum ada data" — bentuknya
     * sendiri yang menyatakan bahwa isinya memang belum ada, bukan sekadar
     * warna yang lebih pucat.
     */
    $varianKelas = [
        'netral' => 'bg-surface-inset text-ink-secondary',
        'bertepi' => 'border border-line bg-surface-raised text-ink-secondary',
        'kosong' => 'border border-dashed border-ink-faint bg-surface-sunken text-ink-muted',
        'sukses' => 'border border-success-line bg-success-soft text-success',
        'perhatian' => 'border border-warning-line bg-warning-soft text-warning',
        'bahaya' => 'border border-danger-line bg-danger-soft text-danger',
        'info' => 'border border-info-line bg-info-soft text-info',
    ];

    $ikonBawaan = [
        'sukses' => 'check',
        'perhatian' => 'clock',
        'bahaya' => 'x',
        'info' => 'info',
    ];

    $ikonDipakai = $ikon ?? ($ikonBawaan[$varian] ?? null);
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex min-h-6 items-center gap-1.5 rounded-[var(--radius-kecil)] px-[9px] py-[3px] text-[12.5px] leading-[1.35] font-medium '
        .($varianKelas[$varian] ?? $varianKelas['netral']),
]) }}>
    @if ($ikonDipakai)
        <x-si.ikon :nama="$ikonDipakai" class="size-3.5 shrink-0" />
    @endif
    {{ $slot }}
</span>
