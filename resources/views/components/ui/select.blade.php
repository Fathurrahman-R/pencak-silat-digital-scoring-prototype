@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $invalid = $errors->has($errorKey);
    $id = $attributes->get('id', $name);
    $current = old($errorKey, $selected);
    $multiple = $attributes->has('multiple');
    $currentValues = $multiple ? (array) $current : [$current];

    /*
     * Panah digambar sendiri lewat background-image, dan payload SVG-nya
     * dipersen-encode.
     *
     * Versi sebelumnya menyisipkan SVG apa adanya di dalam url("…"). Tanda
     * kutip ganda itu menutup atribut style lebih awal, sehingga seluruh
     * atribut sesudahnya — termasuk class — ikut hilang saat browser mengurai
     * halaman. Akibatnya select tampil tanpa gaya sama sekali, dan itu tidak
     * terlihat dari kode sumber maupun dari hasil render Blade; hanya dari DOM
     * yang sudah diurai.
     */
    $panah = rawurlencode(
        "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'"
        ." fill='none' stroke='#6c717c' stroke-width='2' stroke-linecap='round'"
        ." stroke-linejoin='round'><path d='m6 9 6 6 6-6'/></svg>",
    );
@endphp

<div>
    @if ($label)
        <x-ui.label :for="$id" :required="$required" :invalid="$invalid">{{ $label }}</x-ui.label>
    @endif

    {{--
        Appearance dimatikan dan panahnya digambar sendiri, supaya bentuknya
        sama di Chrome, Firefox, dan Safari.
    --}}
    <select id="{{ $id }}"
            name="{{ $multiple ? $name.'[]' : $name }}"
            @required($required)
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @style([
                "background-image:url(data:image/svg+xml,{$panah});background-repeat:no-repeat;background-position:right 12px center" => ! $multiple,
            ])
            {{ $attributes->class([
                'block w-full rounded-md border bg-surface-sunken px-3 text-sm text-ink shadow-well',
                'outline-none transition focus:border-accent focus:ring-3 focus:ring-accent-soft',
                'h-control cursor-pointer appearance-none pe-9' => ! $multiple,
                'py-2' => $multiple,
                'border-line' => ! $invalid,
                'border-danger' => $invalid,
            ]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, array_map('strval', $currentValues), true))>
                {{ $optionLabel }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    <x-ui.field-note :id="$id.'-error'" :error="$invalid ? $errors->first($errorKey) : null" :hint="$hint" />
</div>
