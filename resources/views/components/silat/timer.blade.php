@props([
    'sisaMs' => 0,
    'babak' => 1,
    'jumlahBabak' => null,
    'ukuran' => 'papan',
])

@php
    /*
     * Timer babak.
     *
     * Waktu resmi selalu milik server. Komponen ini hanya menampilkan angka
     * yang dikirim server dan menginterpolasi di antara tick supaya halus; ia
     * tidak pernah menghitung sendiri sisa waktunya. Jam perangkat juri dan
     * operator tidak dipercaya sama sekali — kalau dipercaya, dua panel bisa
     * menampilkan sisa waktu berbeda pada partai yang sama.
     */
    $jumlahBabak ??= config('scoring.tanding.babak.dewasa.jumlah', 3);

    $ukuranKelas = [
        'papan' => 'text-[52px] leading-none',
        'overlay' => 'text-[30px] leading-none',
        'panel' => 'text-[40px] leading-none',
    ];

    $awal = (int) $sisaMs;
@endphp

<div
    x-data="silatTimer({{ $awal }})"
    {{ $attributes->merge(['class' => 'text-center']) }}
>
    <p class="text-[12px] tracking-[.1em] text-silat-teks-redup">
        Babak {{ $babak }}<span class="text-silat-teks-samar">/{{ $jumlahBabak }}</span>
    </p>

    <p
        class="silat-angka font-medium text-silat-teks {{ $ukuranKelas[$ukuran] ?? $ukuranKelas['papan'] }}"
        x-text="tampil"
        role="timer"
        aria-live="off"
    >{{ sprintf('%02d:%02d', intdiv((int) ceil($awal / 1000), 60), ((int) ceil($awal / 1000)) % 60) }}</p>
</div>
