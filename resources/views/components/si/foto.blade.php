@props([
    'user' => null,
    'src' => null,
    'alt' => null,
    'ukuran' => 'sedang',
    // 'orang' bulat, 'badan' persegi — bentuknya yang membedakan, bukan isinya.
    'bentuk' => 'orang',
])

{{--
    Foto orang atau lambang kontingen.

    `alt` selalu terisi: kalau gambarnya gagal dimuat — dan di gelanggang dengan
    sambungan seluler itu sering — yang tersisa hanya teksnya, dan "Avatar"
    tidak memberi tahu siapa. Nama pemiliknya dipakai sebagai bawaan.
--}}

@php
    $ukuranKelas = [
        'kecil' => 'size-8',
        'sedang' => 'size-10',
        'besar' => 'size-16',
        'lebar' => 'size-24',
    ];

    $src ??= $user?->avatarUrl();
    $alt ??= $user?->name ?? 'Foto';
@endphp

<img src="{{ $src }}" alt="{{ $alt }}"
     {{ $attributes->class([
         'shrink-0 object-cover ring-1 ring-line',
         'rounded-full' => $bentuk !== 'badan',
         'rounded-[var(--radius-kecil)]' => $bentuk === 'badan',
         $ukuranKelas[$ukuran] ?? $ukuranKelas['sedang'],
     ]) }}>
