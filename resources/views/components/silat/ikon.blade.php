@props([
    'nama',
    'ukuran' => 24,
    'label' => null,
])

@php
    /*
     * Piktogram aksi dan sanksi pencak silat.
     *
     * Digambar sendiri karena tidak ada set ikon umum — Lucide yang sudah ada
     * di boilerplate sekalipun — yang punya lambang untuk pukulan, tendangan,
     * atau jatuhan.
     *
     * Siluet padat, bukan garis. Ikon garis hilang bentuknya pada 20px di
     * overlay siaran dan dari jarak jauh di layar gelanggang; siluet bertahan.
     *
     * SVG inline, bukan PNG. Kanvas overlay 1920x1080 akan membuat raster
     * pecah, browser engine di dalam vMix ikut terbebani, dan `currentColor`
     * memungkinkan satu ikon melayani sudut merah maupun biru tanpa berkas
     * kembar. Tidak ada permintaan berkas tambahan — syarat gelanggang offline.
     *
     * Tangga sanksi dibedakan lewat bentuk, bukan cuma warna: telapak terbuka
     * (pembinaan) naik ke segitiga (teguran) lalu ke segi delapan (peringatan).
     * Urutan itu sudah dikenal orang dari rambu jalan, jadi tingkat
     * keparahannya terbaca sebelum warnanya sempat dicerna — dan tetap terbaca
     * oleh penonton yang sulit membedakan warna.
     */
    $bentuk = match ($nama) {
        // Pesilat memukul lurus ke kanan dengan kuda-kuda terbuka.
        'pukulan' => '
            <circle cx="8.2" cy="4.4" r="2.6"/>
            <circle cx="20.8" cy="9.2" r="2"/>
            <g fill="none" stroke="currentColor" stroke-width="3.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8.6 7.6 9.8 13.4"/>
                <path d="M9.8 13.4 4.8 20.6"/>
                <path d="M9.8 13.4 15.8 19.8"/>
                <path d="M9.2 9.4 19.6 9.2"/>
                <path d="M9.4 10.8 5.2 13"/>
            </g>',

        // Pesilat melancarkan tendangan tinggi. Tungkai penendang dibuat paling
        // panjang dan paling menonjol — itu yang membedakannya dari ikon
        // pukulan begitu ukurannya mengecil.
        'tendangan' => '
            <circle cx="8.6" cy="4.6" r="2.6"/>
            <g fill="none" stroke="currentColor" stroke-width="3.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 7.8 10.4 13.8"/>
                <path d="M10.4 13.8 20.6 5.4"/>
                <path d="M10.4 13.8 8 20.8"/>
                <path d="M9.4 9.6 4.6 7.8"/>
                <path d="M9.6 10.8 5.6 13.6"/>
            </g>',

        // Pesilat terjatuh di atas matras. Bilah bawah adalah matrasnya, dan
        // badan yang mendatar di atasnya yang membuat arah jatuh terbaca.
        'jatuhan' => '
            <circle cx="4.8" cy="7.6" r="2.6"/>
            <g fill="none" stroke="currentColor" stroke-width="3.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7.4 8.2 15 6.6"/>
                <path d="M15 6.6 20.4 3.4"/>
                <path d="M15 6.6 20.8 9.6"/>
                <path d="M9.6 7.8 7.4 13.8"/>
            </g>
            <rect x="1.6" y="17.8" width="20.8" height="2.4" rx="1.2"/>',

        // Telapak terbuka: isyarat membina, bukan menghukum.
        'pembinaan' => '
            <rect x="7.4" y="5.2" width="2.2" height="7" rx="1.1"/>
            <rect x="10" y="3.8" width="2.2" height="8.4" rx="1.1"/>
            <rect x="12.6" y="4.2" width="2.2" height="8" rx="1.1"/>
            <rect x="15.2" y="5.6" width="2.2" height="6.6" rx="1.1"/>
            <rect x="6.6" y="9.6" width="11" height="10.4" rx="3.4"/>
            <rect x="3" y="10.4" width="5" height="2.8" rx="1.4" transform="rotate(-28 5.5 11.8)"/>',

        // Segitiga dengan tanda seru dilubangi. Lubangnya memakai fill-rule
        // evenodd supaya tetap tembus di atas warna sudut apa pun.
        'teguran' => '
            <path fill-rule="evenodd" d="M12 2.6 22.9 21.4H1.1Z M11 9.4h2v5.8h-2Z M11 16.8h2v2.1h-2Z"/>',

        // Segi delapan, bentuk rambut berhenti. Tingkat paling berat sebelum
        // diskualifikasi.
        'peringatan' => '
            <path fill-rule="evenodd" d="M8.7 1.8h6.6l6.9 6.9v6.6l-6.9 6.9H8.7l-6.9-6.9V8.7Z M11 6.6h2v7.2h-2Z M11 15.4h2v2.2h-2Z"/>',

        default => '',
    };

    // Label baku dipakai pembaca layar dan berita acara berbasis teks, sehingga
    // hasil pertandingan tetap terbaca tanpa melihat ikonnya.
    $labelBaku = [
        'pukulan' => 'Pukulan, nilai 1',
        'tendangan' => 'Tendangan, nilai 2',
        'jatuhan' => 'Jatuhan, nilai 3',
        'pembinaan' => 'Pembinaan',
        'teguran' => 'Teguran',
        'peringatan' => 'Peringatan',
    ];

    $teksLabel = $label ?? ($labelBaku[$nama] ?? null);
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    width="{{ $ukuran }}"
    height="{{ $ukuran }}"
    fill="currentColor"
    @if ($teksLabel) role="img" aria-label="{{ $teksLabel }}" @else aria-hidden="true" @endif
    {{ $attributes->merge(['class' => 'inline-block shrink-0']) }}
>{!! $bentuk !!}</svg>
