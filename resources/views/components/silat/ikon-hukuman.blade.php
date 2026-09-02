@props([
    // pembinaan | teguran | peringatan
    'jenis',

    // Tingkat ke berapa dalam tangganya: 1 atau 2. Pembinaan dan Teguran
    // punya isyarat tangan yang BERBEDA per tingkat (satu jari lalu dua jari);
    // Peringatan memakai isyarat yang sama di kedua tingkatnya.
    'tingkat' => 1,

    // Petak yang sudah menyala digambar gelap di atas bidang terang; yang
    // belum, terang di atas bidang gelap.
    'nyala' => false,

    /*
     * TINGGI gambar; lebarnya mengikuti rasio aslinya.
     *
     * Dipatok persegi, siluet Peringatan -- yang jauh lebih tinggi daripada
     * lebar -- mengecil sampai tinggal noktah demi memenuhi lebar kotak, dan
     * isyarat yang paling berat justru jadi yang paling tidak terbaca.
     */
    'ukuran' => 12,
])

{{--
    Isyarat tangan wasit untuk tiap tingkat hukuman, difoto dari isyarat yang
    benar-benar dipakai di gelanggang — bukan piktogram bikinan sendiri.

    Berkasnya hitam pekat di atas latar tembus pandang. Petak yang menyala
    berlatar terang, jadi gambarnya dipakai apa adanya; petak yang belum
    menyala berlatar gelap, dan gambarnya dibalik warnanya (`invert`) supaya
    isyaratnya tetap terbaca alih-alih lenyap ke dalam latar.

    Dipakai bersama panel operator, panel wasit, live score publik, dan overlay
    siaran — satu berkas gambar untuk semuanya, supaya isyarat yang dilihat
    petugas di tepi matras sama persis dengan yang tayang di siaran.
--}}

@php
    $berkas = match ($jenis) {
        'pembinaan' => 'pembinaan-'.min(2, max(1, (int) $tingkat)),
        'teguran' => 'teguran-'.min(2, max(1, (int) $tingkat)),
        'peringatan' => 'peringatan-'.min(2, max(1, (int) $tingkat)),
        default => null,
    };

    $namaTingkat = ['pembinaan' => 'Pembinaan', 'teguran' => 'Teguran', 'peringatan' => 'Peringatan'][$jenis] ?? $jenis;
@endphp

@if ($berkas)
    <img src="{{ asset('img/hukuman/'.$berkas.'.png') }}"
         alt="{{ $namaTingkat }} {{ $tingkat }}"
         aria-hidden="true"
         {{ $attributes->class(['w-auto max-w-full shrink-0 object-contain', 'invert' => ! $nyala, 'opacity-70' => ! $nyala]) }}
         style="height: {{ $ukuran }}px">
@endif
