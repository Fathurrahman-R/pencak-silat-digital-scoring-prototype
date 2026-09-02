@props([
    'label',
    'nilai',
    'keterangan' => null,
    'ikon' => null,
])

{{--
    Satu angka yang menjawab satu pertanyaan panitia: berapa yang sudah bayar,
    berapa yang belum, berapa uangnya.

    Angkanya pakai digit tabular supaya sebaris kartu berjajar rapi -- di font
    biasa angka 1 lebih sempit dari 8, dan kolom rupiah jadi bergoyang.

    Pendahulunya punya prop `trend` dengan panah naik-turun berwarna. Itu
    dibuang: di layar bendahara tidak ada satu pun angka yang punya arah -- 
    "12 kontingen lunas" tidak naik atau turun terhadap apa pun. Panah yang
    tidak berarti membuat orang mencari arti yang tidak ada. Kalau suatu saat
    ada angka yang memang punya pembanding, `keterangan` menampung kalimatnya.
--}}

{{-- DESIGN-SYSTEM.md §8: label 12.5/500, nilai mono 26px, sub 12.5px. Maksimal
     empat per baris; tidak ada grafik di dalamnya. --}}
<div {{ $attributes->class('rounded-[var(--radius-besar)] border border-line bg-surface-raised px-4.5 py-4') }}>
    <div class="flex items-start justify-between gap-3">
        <span class="text-[12.5px] font-medium text-ink-muted">{{ $label }}</span>

        @if ($ikon)
            <x-si.ikon :nama="$ikon" class="size-4 shrink-0 text-ink-muted" />
        @endif
    </div>

    <div class="mt-1.5 font-mono text-[26px] leading-none font-medium tracking-[-0.02em] tabular-nums text-ink">{{ $nilai }}</div>

    @if ($keterangan)
        <p class="mt-1.5 text-[12.5px] text-ink-faint">{{ $keterangan }}</p>
    @endif
</div>
