@props([
    'judul',
    'keterangan' => null,
])

{{--
    Satu baris formulir — DESIGN-SYSTEM.md §8.

    Grid 260px + sisa: penjelasan di kiri, kontrolnya di kanan. Bentuk ini
    dipakai supaya setiap isian punya tempat untuk kalimat yang menyatakan
    prasyaratnya — dan prasyarat yang dinyatakan adalah alasan seluruh layar
    setelan bisa dipakai panitia yang membukanya sekali setahun.
--}}

<div {{ $attributes->merge(['class' => 'grid gap-x-8 gap-y-4 border-b border-line px-5.5 py-5 last:border-b-0 md:grid-cols-[260px_1fr]']) }}>
    <div class="min-w-0">
        <p class="text-[14.5px] font-semibold text-ink">{{ $judul }}</p>
        @if ($keterangan)
            <p class="mt-1 text-[13px] leading-[1.55] text-ink-muted">{{ $keterangan }}</p>
        @endif
    </div>

    <div class="flex min-w-0 flex-col gap-4">{{ $slot }}</div>
</div>
