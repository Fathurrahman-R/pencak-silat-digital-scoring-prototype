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
     *
     * Bentuknya kotak bertepi penuh — DESIGN-SYSTEM.md §9 keadaan 4 (galat):
     * radius 12, ikon status, judul semibold, penjelasan di bawahnya. Bukan
     * lagi garis aksen di kiri: garis tunggal terlalu tenang untuk peringatan
     * yang harus dibaca sebelum melangkah.
     */
    $varianKelas = [
        'keterangan' => ['border-line bg-surface-inset text-ink-secondary', null],
        'berhasil' => ['border-success-line bg-success-soft text-ink', 'check'],
        'perhatian' => ['border-warning-line bg-warning-soft text-warning', 'triangle-alert'],
        'bahaya' => ['border-danger-line bg-danger-soft text-danger', 'circle-alert'],
    ];

    [$kelas, $ikon] = $varianKelas[$varian] ?? $varianKelas['keterangan'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-[var(--radius-besar)] border px-4.5 py-4 '.$kelas]) }}>
    @if ($ikon)
        <x-si.ikon :nama="$ikon" class="mt-0.5 size-[18px] shrink-0" />
    @endif
    <div class="min-w-0">
        @if ($judul)
            <p class="text-[14.5px] font-semibold">{{ $judul }}</p>
        @endif
        <div @class(['text-[13.5px] leading-relaxed', 'mt-1' => $judul])>{{ $slot }}</div>
    </div>
</div>
