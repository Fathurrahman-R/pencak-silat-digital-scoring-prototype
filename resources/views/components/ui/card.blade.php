@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'material' => false,
])

{{--
    Card selalu duduk di permukaan solid. Kaca hanya dipakai dua panel shell
    aplikasi (sidebar dan topbar); kartu isi tidak pernah mengambang di
    atasnya — teks, tabel, dan angka jadi lebih sulit dibaca.

    Varian `material` adalah bidang yang menampung kontrol — kelompok tombol,
    tuas, slider. Bukan untuk teks panjang atau tabel.
--}}

<div {{ $attributes->class([
    'rounded-lg',
    'mat-panel' => $material,
    'border border-line bg-surface-raised shadow-lift' => ! $material,
]) }}>
    @if ($title || $subtitle || isset($header))
        <div class="flex items-start justify-between gap-4 border-b border-line px-4 py-3">
            <div>
                @if ($title)
                    <h2 class="font-display text-[15px] font-semibold text-ink">{{ $title }}</h2>
                @endif

                @if ($subtitle)
                    <p class="mt-0.5 text-sm2 text-ink-secondary">{{ $subtitle }}</p>
                @endif

                {{ $header ?? '' }}
            </div>

            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-4' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-line px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
