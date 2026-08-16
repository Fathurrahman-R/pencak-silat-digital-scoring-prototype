@props([
    'nilai' => 0,
    'ukuran' => 'papan',
])

@php
    /*
     * Angka skor. Selalu numeral tabular — lihat `.silat-angka` di silat.css:
     * tanpa itu lebar digit berubah saat skor melewati 9 ke 10 dan angkanya
     * terlihat bergoyang, paling kentara justru saat disiarkan.
     *
     * Ukuran mengikuti jarak baca, bukan selera:
     *   papan   layar gelanggang, dibaca dari tribun
     *   overlay scorebug siaran, berbagi ruang dengan gambar kamera
     *   panel   panel operator dan dewan juri, dibaca dari jarak meja
     *   ringkas riwayat nilai dan daftar
     */
    $ukuranKelas = [
        'papan' => 'text-[104px] leading-[.86]',
        'overlay' => 'text-[44px] leading-none',
        'panel' => 'text-[56px] leading-none',
        'ringkas' => 'text-2xl leading-none',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'silat-angka font-medium tracking-tight '.($ukuranKelas[$ukuran] ?? $ukuranKelas['papan']),
    ]) }}
>{{ $nilai }}</span>
