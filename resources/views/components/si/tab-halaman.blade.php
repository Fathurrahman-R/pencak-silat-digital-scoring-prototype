@props([
    // ['Label' => 'url'] atau ['Label' => ['url', 'pola-aktif']]
    'daftar' => [],
])

{{--
    Tab yang BERPINDAH HALAMAN, bukan berpindah isi.

    Dipakai untuk sekumpulan halaman yang membicarakan satu hal yang sama --
    atlet, pendaftaran, dan tagihan milik satu kontingen. Tab yang
    menyembunyikan isi tidak cocok di situ: masing-masing punya alamatnya
    sendiri, bisa dibagikan ke official, dan tidak layak dimuat sekaligus.

    Tab yang sedang terbuka ditentukan dari alamat yang sedang dibuka, bukan
    dari keadaan di sisi klien, jadi tetap benar setelah halaman dimuat ulang
    maupun dibuka dari tautan yang dikirim orang lain.

    Bedanya ditandai tiga hal sekaligus -- garis bawah, tebal huruf, dan warna
    tinta. Garis sendirian menghilang di proyektor gelanggang yang warnanya
    pudar.
--}}

@php
    $tab = [];

    foreach ($daftar as $label => $isi) {
        [$url, $pola] = is_array($isi) ? $isi : [$isi, null];

        $tab[] = [
            'label' => $label,
            'url' => $url,
            'aktif' => $pola
                ? request()->is($pola)
                : request()->url() === strtok($url, '?'),
        ];
    }
@endphp

<div {{ $attributes->merge(['class' => 'border-b border-line']) }}>
    <ul class="-mb-px flex flex-wrap gap-1">
        @foreach ($tab as $satu)
            <li>
                <a href="{{ $satu['url'] }}"
                   @if ($satu['aktif']) aria-current="page" @endif
                   @class([
                       'inline-flex h-9.5 items-center border-b-2 px-3 text-[13.5px] no-underline outline-none',
                       'focus-visible:ring-[3px] focus-visible:ring-ink/10',
                       'border-accent font-semibold text-ink' => $satu['aktif'],
                       'border-transparent text-ink-muted hover:text-ink' => ! $satu['aktif'],
                   ])>
                    {{ $satu['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
