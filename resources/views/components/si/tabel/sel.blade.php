@props([
    'header' => false,
    'align' => 'left',
    'numeric' => false,
])

{{--
    Satu sel tabel.

    Angka selalu monospasi, rata kanan, dan bernumeral tabular supaya digitnya
    lurus antar-baris — kolom "174" di atas "9" yang tidak lurus menuntut
    pembacanya membandingkan panjang, bukan nilai.
--}}

@php
    $kelas = [
        'px-3.5 py-[11px] align-middle',
        'text-right' => $align === 'right' || $numeric,
        'text-center' => $align === 'center',
        'font-mono tabular-nums' => $numeric,
        'font-medium whitespace-nowrap text-ink' => $header,
    ];
@endphp

@if ($header)
    <th scope="row" {{ $attributes->class($kelas) }}>{{ $slot }}</th>
@else
    <td {{ $attributes->class($kelas) }}>{{ $slot }}</td>
@endif
