@props([
    'name',
    'label' => null,
    'value' => null,
    'bantuan' => null,
    'baris' => 4,
    'wajib' => false,
])

{{--
    Isian untuk kalimat, bukan untuk kata: alasan pembatalan partai, catatan
    ketua pertandingan, keterangan peran.

    Bisa ditarik tinggi (`resize-y`) tapi tidak melebar -- melebar akan
    merusak susunan kolom di sekitarnya, dan orang yang tidak sengaja
    menariknya tidak punya cara mengembalikannya.
--}}

@php
    $kunciGalat = str_replace(['[', ']'], ['.', ''], $name);
    $galat = $errors->has($kunciGalat);
    $idBantuan = $bantuan ? $name.'-bantuan' : null;
    $idGalat = $galat ? $name.'-galat' : null;
    $dijelaskan = trim(($idGalat ?? '').' '.($idBantuan ?? ''));
@endphp

<div class="flex flex-col gap-2">
    @if ($label)
        <label for="{{ $name }}" class="text-[14px] font-semibold text-ink">
            {{ $label }}
            @if ($wajib)
                <span class="font-normal text-ink-muted">— wajib diisi</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $baris }}"
        @required($wajib)
        @if ($dijelaskan) aria-describedby="{{ $dijelaskan }}" @endif
        @if ($galat) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block w-full resize-y rounded-[var(--radius)] border bg-surface-raised px-3.5 py-3 text-[15px] leading-relaxed text-ink outline-none placeholder:text-ink-muted focus:ring-2 focus:ring-surface focus:ring-offset-2 focus:ring-offset-ink '.($galat ? 'border-danger' : 'border-line-strong')]) }}
    >{{ old($kunciGalat, $value) }}</textarea>

    @error($kunciGalat)
        <p id="{{ $idGalat }}" class="text-[14px] leading-relaxed text-danger">{{ $message }}</p>
    @enderror

    @if ($bantuan)
        <p id="{{ $idBantuan }}" class="text-[14px] leading-relaxed text-ink-muted">{{ $bantuan }}</p>
    @endif
</div>
