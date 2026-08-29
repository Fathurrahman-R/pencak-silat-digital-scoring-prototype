@props([
    // 'aktif' | 'pergi' | 'mati'
    'keadaan' => 'aktif',
])

{{--
    Titik kecil di sudut foto yang menyatakan keadaan akun.

    Titik ini TIDAK PERNAH jadi satu-satunya pembawa keadaan: warnanya kecil,
    dan yang sulit membedakan hijau dari abu-abu tidak punya cara lain
    membacanya. Kata-katanya selalu ada untuk pembaca layar, dan di layar
    daftar keadaannya diulang sebagai badge berkata.

    `title` disertakan untuk kursor, tapi tidak diandalkan: ia tidak pernah
    muncul di layar sentuh.
--}}

@php
    $warna = [
        'aktif' => 'bg-success',
        'pergi' => 'bg-warning',
        'mati' => 'bg-line-strong',
    ];

    $kata = [
        'aktif' => 'Sedang aktif',
        'pergi' => 'Sedang pergi',
        'mati' => 'Tidak aktif',
    ];
@endphp

<span {{ $attributes->class([
    'absolute end-[-1px] bottom-[-1px] size-[11px] rounded-full ring-2 ring-surface-raised',
    $warna[$keadaan] ?? $warna['mati'],
]) }} title="{{ $kata[$keadaan] ?? '' }}">
    <span class="sr-only">{{ $kata[$keadaan] ?? '' }}</span>
</span>
