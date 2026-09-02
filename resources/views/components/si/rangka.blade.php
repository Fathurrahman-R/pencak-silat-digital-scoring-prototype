@props([
    // Jumlah baris rangka yang digambar.
    'baris' => 4,
    // Tinggi tiap blok, 12–16px menurut §9.
    'tinggi' => 14,
])

{{--
    Keadaan MEMUAT — DESIGN-SYSTEM.md §9 keadaan 3.

    Rangka isi, bukan spinner. Spinner cuma menyatakan "sedang sibuk"; rangka
    menyatakan bentuk apa yang sebentar lagi muncul, jadi mata sudah berada di
    tempat yang benar begitu datanya tiba.

    Lebarnya sengaja bervariasi 40–100%: rangka yang semua barisnya sama panjang
    terbaca sebagai tabel kosong, bukan sebagai isi yang sedang dimuat.
--}}

@php
    $lebar = [92, 68, 100, 54, 84, 40, 76];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-2.5']) }} aria-hidden="true">
    @for ($i = 0; $i < (int) $baris; $i++)
        <span class="block animate-denyut rounded-[var(--radius)] bg-surface-inset"
              style="height: {{ (int) $tinggi }}px; width: {{ $lebar[$i % count($lebar)] }}%;"></span>
    @endfor
</div>
