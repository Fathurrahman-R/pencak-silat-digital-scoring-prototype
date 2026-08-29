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

<div {{ $attributes->class('flex flex-col gap-1 rounded-[var(--radius)] border border-line bg-surface-raised p-4') }}>
    <div class="flex items-start justify-between gap-3">
        <span class="text-[13px] font-semibold text-ink-muted">{{ $label }}</span>

        @if ($ikon)
            <x-si.ikon :nama="$ikon" class="size-4 shrink-0 text-ink-muted" />
        @endif
    </div>

    <div class="text-[26px] leading-tight font-semibold tabular-nums text-ink">{{ $nilai }}</div>

    @if ($keterangan)
        <p class="text-[14px] leading-relaxed text-ink-muted">{{ $keterangan }}</p>
    @endif
</div>
