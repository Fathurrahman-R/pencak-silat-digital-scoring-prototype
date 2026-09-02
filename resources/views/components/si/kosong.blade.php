@props([
    'judul' => 'Belum ada isinya',

    // WAJIB diisi: keadaan kosong harus menyebutkan APA YANG MEMBUKA ISINYA,
    // bukan sekadar "tidak ada data". Panitia yang membuka layar kosong perlu
    // tahu langkah apa yang membuatnya terisi.
    'syarat',

    'ikon' => 'inbox',
])

{{--
    Zona kosong — DESIGN-SYSTEM.md §9 keadaan 2: padding 64/24, radius 12,
    tepi putus-putus, ikon dalam kotak 44px, satu tombol utama.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-[var(--radius-besar)] border border-dashed border-line-strong bg-surface-sunken px-6 py-16 text-center']) }}>
    @if ($ikon)
        <span class="grid size-11 place-items-center rounded-[var(--radius)] border border-line bg-surface-raised text-ink-secondary">
            <x-si.ikon :nama="$ikon" class="size-5" />
        </span>
    @endif

    <p class="text-[16px] font-semibold text-ink">{{ $judul }}</p>
    <p class="max-w-[420px] text-[14px] leading-relaxed text-ink-muted">{{ $syarat }}</p>

    @isset($aksi)
        <div class="mt-1">{{ $aksi }}</div>
    @endisset
</div>
