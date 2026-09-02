@props([
    'name',
    'label' => null,
    'tipe' => 'text',
    'value' => null,
    'bantuan' => null,
    'wajib' => false,
    'awalan' => null,
    'nonaktif' => false,
])

@php
    $galat = $errors->has($name);
    $idBantuan = $bantuan ? $name.'-bantuan' : null;
    $idGalat = $galat ? $name.'-galat' : null;
    $dijelaskan = trim(($idGalat ?? '').' '.($idBantuan ?? ''));
@endphp

<div class="flex flex-col gap-1.5">
    @if ($label)
        <label for="{{ $name }}" class="text-[13px] font-medium text-ink">
            {{ $label }}
            @if ($wajib)
                {{-- Tanda wajib berupa kata, bukan tanda bintang: bintang hanya
                     dipahami orang yang sudah terbiasa mengisi formulir web. --}}
                <span class="font-normal text-ink-muted">— wajib diisi</span>
            @endif
        </label>
    @endif

    <div @class([
        'flex items-center gap-2 rounded-[var(--radius)] border bg-surface-raised',
        'min-h-[var(--sentuh-admin)] px-3',
        'focus-within:border-accent focus-within:ring-[3px] focus-within:ring-ink/10',
        'border-danger-line' => $galat,
        'border-line-strong' => ! $galat,
        'opacity-45' => $nonaktif,
    ])>
        @if ($awalan)
            <span class="shrink-0 text-[13.5px] text-ink-muted">{{ $awalan }}</span>
        @endif

        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $tipe }}"
            value="{{ old($name, $value) }}"
            @required($wajib)
            @disabled($nonaktif)
            @if ($dijelaskan) aria-describedby="{{ $dijelaskan }}" @endif
            @if ($galat) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'w-full bg-transparent py-[7px] text-[13.5px] text-ink outline-none placeholder:text-ink-muted']) }}
        >
    </div>

    {{--
        Pesan galat menyebut apa yang harus dilakukan, bukan apa yang gagal --
        itu tanggung jawab FormRequest. Di sini ia hanya ditampilkan, dan
        ditampilkan DI ATAS keterangan bantu supaya yang mendesak dibaca lebih
        dulu.
    --}}
    @error($name)
        <p id="{{ $idGalat }}" class="text-[12.5px] leading-relaxed text-danger">{{ $message }}</p>
    @enderror

    @if ($bantuan)
        <p id="{{ $idBantuan }}" class="text-[12.5px] leading-relaxed text-ink-muted">{{ $bantuan }}</p>
    @endif
</div>
