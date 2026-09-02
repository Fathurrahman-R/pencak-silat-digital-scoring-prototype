@props([
    'kolom',        // array<string, array{jumlah:int}>
    'sisi',         // 'merah' | 'biru' -- kunci di dalam objek `hukuman` milik overlayLive
    'rata' => 'kiri',
    'ukuran' => 24,
])

{{--
    Petak hukuman untuk overlay siaran.

    Bedanya dengan <x-silat.baris-hukuman>: komponen itu menerima jumlah terisi
    sebagai nilai PHP yang sudah tetap saat halaman digambar. Overlay tidak
    pernah dimuat ulang — ia menyala berjam-jam dan seluruh isinya berubah lewat
    Alpine — jadi jumlah petak yang menyala di sini harus dibaca reaktif dari
    `hukuman[sisi]`, bukan disuntik sekali di server.

    Bentuknya mengikuti panel: petak berikon, terisi maupun belum. Yang berubah
    hanya ukurannya.

    Petak "belum terisi" dulu berupa bidang `bg-black/30`, dan itu berkontras
    1.69 di atas bidang merah sudut serta 1.40 di atas biru — penonton siaran
    praktis tidak melihatnya. Sekarang ia berupa tepi putih, yang di atas kedua
    bidang sudut terukur 5.20 dan 9.04.

    Tidak ada teks label. Ruang di bar sempit dan namanya sudah dibawa overlay
    breakdown; yang dibutuhkan penonton di bar utama hanya "ada sanksi berjalan,
    sebanyak ini, seberat ini".
--}}

@php
    $kananDulu = $rata === 'kanan';

    // Bidang sudut sudah berwarna, jadi keparahan di sini dibawa terang-gelap,
    // bukan warna: makin berat makin pekat putihnya. Bentuk ikon tetap
    // membedakan ketiganya, sehingga tidak ada informasi yang hanya bergantung
    // pada warna.
    $nyalaBidang = [
        'pembinaan' => 'bg-white/50',
        'teguran' => 'bg-white/80',
        'peringatan' => 'bg-white',
    ];

    $ikonPx = (int) round($ukuran * 0.6);
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 '.($kananDulu ? 'flex-row-reverse' : '')]) }}
     aria-hidden="true">
    @foreach ($kolom as $jenis => $gaya)
        <div class="flex gap-1 {{ $kananDulu ? 'flex-row-reverse' : '' }}">
            @for ($i = 1; $i <= $gaya['jumlah']; $i++)
                @php($diskualifikasi = $jenis === 'peringatan' && $i === $gaya['jumlah'])
                <span class="flex shrink-0 items-center justify-center rounded-silat-kecil border border-white/70 {{ $diskualifikasi ? 'border-dashed' : '' }}"
                      style="width: {{ $ukuran }}px; height: {{ $ukuran }}px;"
                      x-bind:class="(hukuman?.{{ $sisi }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                          ? '{{ $nyalaBidang[$jenis] ?? 'bg-white' }} border-transparent'
                          : ''">
                    <x-silat.ikon :nama="$jenis" :ukuran="$ikonPx" :label="null"
                                  x-bind:class="(hukuman?.{{ $sisi }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                                      ? 'text-black/85'
                                      : 'text-white/70'" />
                </span>
            @endfor
        </div>
    @endforeach
</div>
