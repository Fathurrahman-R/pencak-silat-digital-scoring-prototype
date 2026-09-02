@props([
    'judul' => null,
    'keterangan' => null,
    'padat' => false,
])

{{--
    Kartu adalah permukaan naik dengan satu garis -- tanpa kaca, tanpa bayangan
    bertumpuk, tanpa gradien. Kedalaman dinyatakan warna permukaan; bayangan
    hanya untuk lapisan yang benar-benar mengambang (dropdown, modal).
--}}
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-besar)] border border-line bg-surface-raised']) }}>
    @if ($judul || isset($aksi))
        <div class="flex items-start justify-between gap-4 border-b border-line px-3.5 py-3">
            <div class="min-w-0">
                @if ($judul)
                    <h2 class="text-[13px] font-semibold text-ink">{{ $judul }}</h2>
                @endif
                @if ($keterangan)
                    <p class="mt-1 max-w-[68ch] text-[12.5px] leading-relaxed text-ink-muted">{{ $keterangan }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="flex shrink-0 items-center gap-2">{{ $aksi }}</div>
            @endisset
        </div>
    @endif

    <div @class(['px-4.5', 'py-3' => $padat, 'py-4.5' => ! $padat])>
        {{ $slot }}
    </div>
</div>
