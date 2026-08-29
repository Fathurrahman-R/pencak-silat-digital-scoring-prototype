@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'bantuan' => null,
    'wajib' => false,
    'nonaktif' => false,
])

@php
    // Nama bertanda kurung (`juri_id[0]`) jadi kunci galat bertitik.
    $kunciGalat = str_replace(['[', ']'], ['.', ''], $name);
    $galat = $errors->has($kunciGalat);
    $id = $attributes->get('id', $name);
    $terpilih = old($kunciGalat, $selected);
    $banyak = $attributes->has('multiple');
    $nilaiTerpilih = $banyak ? (array) $terpilih : [$terpilih];

    /*
     * Panah digambar sendiri lewat background-image, dan payload SVG-nya
     * dipersen-encode.
     *
     * Menyisipkan SVG apa adanya di dalam url("…") menutup atribut style lebih
     * awal begitu ada tanda kutip ganda di dalamnya, sehingga seluruh atribut
     * sesudahnya — termasuk class — ikut hilang saat peramban mengurai
     * halaman. Akibatnya select tampil tanpa gaya sama sekali, dan itu tidak
     * terlihat dari kode sumber maupun dari hasil render Blade; hanya dari DOM
     * yang sudah diurai. Cacat yang sama pernah terjadi di pendahulunya.
     */
    $panah = rawurlencode(
        "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'"
        ." fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round'"
        ." stroke-linejoin='round'><path d='m6 9 6 6 6-6'/></svg>",
    );
@endphp

{{--
    Pilihan (select).

    Bentuk asli peramban dipertahankan, bukan diganti daftar buatan sendiri:
    di HP, select bawaan membuka pemilih layar penuh milik sistem — lebih besar
    sasarannya, bisa dicari dengan mengetik, dan sudah dikenal orang yang tidak
    terbiasa dengan aplikasi web. Yang diganti hanya rupanya.
--}}

<div class="flex flex-col gap-2">
    @if ($label)
        <label for="{{ $id }}" class="text-[14px] font-semibold text-ink">
            {{ $label }}
            @if ($wajib)
                {{-- Tanda wajib berupa kata, bukan tanda bintang. --}}
                <span class="font-normal text-ink-muted">— wajib diisi</span>
            @endif
        </label>
    @endif

    <select id="{{ $id }}"
            name="{{ $banyak ? $name.'[]' : $name }}"
            @required($wajib)
            @disabled($nonaktif)
            @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
            @style([
                "background-image:url(data:image/svg+xml,{$panah});background-repeat:no-repeat;background-position:right 12px center" => ! $banyak,
            ])
            {{ $attributes->class([
                'w-full rounded-[var(--radius)] border bg-surface-raised px-3.5 text-[15px] text-ink outline-none',
                'h-[var(--sentuh-admin)] cursor-pointer appearance-none pe-9' => ! $banyak,
                'py-2' => $banyak,
                'border-danger' => $galat,
                'border-line-strong' => ! $galat,
                'opacity-45' => $nonaktif,
            ]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $nilai => $teks)
            <option value="{{ $nilai }}" @selected(in_array((string) $nilai, array_map('strval', $nilaiTerpilih), true))>
                {{ $teks }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    {{--
        Pesan galat menyebut apa yang harus dilakukan, bukan apa yang gagal.
        Ditampilkan DI ATAS keterangan bantu supaya yang mendesak dibaca dulu.
    --}}
    @error($kunciGalat)
        <p id="{{ $id }}-galat" class="text-[14px] leading-relaxed text-danger">{{ $message }}</p>
    @enderror

    @if ($bantuan)
        <p class="text-[14px] leading-relaxed text-ink-muted">{{ $bantuan }}</p>
    @endif
</div>
