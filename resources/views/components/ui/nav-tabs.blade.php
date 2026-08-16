@props([
    'items' => [],
])

{{--
    Tab yang berpindah halaman, bukan berpindah isi.

    Berbeda dengan <x-ui.tabs> yang menyembunyikan dan menampilkan panel di
    halaman yang sama, komponen ini berisi tautan sungguhan. Dipakai untuk
    sekumpulan halaman yang membicarakan satu hal yang sama — misalnya atlet,
    pendaftaran, dan tagihan milik satu kontingen. Tab yang menyembunyikan isi
    tidak cocok di situ: masing-masing punya alamatnya sendiri, bisa dibagikan,
    dan tidak layak dimuat sekaligus.

    $items berbentuk ['Label' => 'url'] atau ['Label' => ['url', 'pola-aktif']].
    Tab aktif ditentukan dari alamat yang sedang dibuka, bukan dari keadaan di
    sisi klien, sehingga tetap benar setelah halaman dimuat ulang.
--}}

@php
    $tabs = [];

    foreach ($items as $label => $isi) {
        [$url, $pola] = is_array($isi) ? $isi : [$isi, null];

        $tabs[] = [
            'label' => $label,
            'url' => $url,
            'active' => $pola
                ? request()->is($pola)
                : request()->url() === strtok($url, '?'),
        ];
    }
@endphp

<div {{ $attributes->merge(['class' => 'border-b border-line']) }}>
    <ul class="-mb-px flex flex-wrap gap-1">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ $tab['url'] }}"
                   @if ($tab['active']) aria-current="page" @endif
                   class="inline-block border-b-2 px-3 py-3.5 text-sm transition outline-none focus-visible:ring-3 focus-visible:ring-accent-soft
                          {{ $tab['active']
                              ? 'border-accent font-semibold text-ink'
                              : 'border-transparent text-ink-secondary hover:text-ink' }}">
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
