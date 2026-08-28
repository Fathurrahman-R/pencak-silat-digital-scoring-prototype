@props([
    'name',
    'label' => null,
    'value' => '1',
    'dicentang' => false,
    'bantuan' => null,
    'nonaktif' => false,
])

@php
    // Nama bertanda kurung (`izin[bagan]`) jadi kunci galat bertitik.
    $kunciGalat = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', $name.'_'.$value);
    $galat = $errors->has($kunciGalat);
@endphp

{{--
    Kotak centang.

    Yang bisa ditekan adalah SELURUH baris, bukan kotak 16px-nya saja. Kotak
    sekecil itu meleset terus di layar sentuh, dan orang yang tidak terbiasa
    dengan formulir web akan menyimpulkan kotaknya rusak, bukan bahwa
    tekanannya meleset. Labelnya membungkus kotak sehingga tinggi sasaran
    mengikuti tinggi baris.

    Kotaknya bertepi tebal saat kosong dan bidang penuh saat dicentang. Beda
    keduanya adalah terang-gelap, bukan rona, jadi tetap terbaca oleh mata
    yang tidak membedakan warna -- dan tanda centangnya sendiri sudah bentuk.

    Tanda centang berupa <svg> saudara yang muncul lewat `peer-checked`, bukan
    latar `bg-[url(data:image/svg+xml...)]` pada input. Bentuk yang terakhir
    tidak pernah dihasilkan Tailwind -- tanda kutip di dalam URL data memutus
    pembacaan nama kelasnya -- sehingga kotak yang tercentang jadi bidang
    terang polos tanpa tanda apa pun. Bukan pula glif huruf, yang berubah
    bentuk mengikuti fon sistem tiap perangkat.
--}}

<div>
    <label for="{{ $id }}"
           @class([
               'flex min-h-[44px] cursor-pointer items-center gap-3 py-1',
               'cursor-not-allowed opacity-45' => $nonaktif,
           ])>
        <span class="relative inline-flex shrink-0">
            <input type="checkbox"
                   id="{{ $id }}"
                   name="{{ $name }}"
                   value="{{ $value }}"
                   @checked($dicentang)
                   @disabled($nonaktif)
                   @if ($galat) aria-invalid="true" aria-describedby="{{ $name }}-galat" @endif
                   {{ $attributes->class([
                       'peer size-[22px] appearance-none rounded-[var(--radius-kecil)] border-2 bg-surface-raised',
                       'checked:border-accent checked:bg-accent',
                       'focus-visible:ring-2 focus-visible:ring-surface focus-visible:ring-offset-2 focus-visible:ring-offset-ink focus-visible:outline-none',
                       'border-danger' => $galat,
                       'border-line-strong' => ! $galat,
                   ]) }}>

            <svg class="pointer-events-none absolute inset-0 m-auto hidden size-[14px] text-accent-on peer-checked:block"
                 viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3.5 8.5l3 3 6-6" stroke="currentColor" stroke-width="2.5"
                      stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>

        <span class="min-w-0">
            @if ($label)
                <span class="block text-[15px] text-ink">{{ $label }}</span>
            @endif
            @if ($bantuan)
                <span class="block text-[13px] leading-relaxed text-ink-muted">{{ $bantuan }}</span>
            @endif
        </span>
    </label>

    @error($kunciGalat)
        <p id="{{ $name }}-galat" class="text-[13px] text-danger">{{ $message }}</p>
    @enderror
</div>
