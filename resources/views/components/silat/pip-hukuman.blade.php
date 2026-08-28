@props([
    'kolom',        // array<string, array{jumlah:int, nyala:string}>
    'sisi',         // 'merah' | 'biru' -- kunci di dalam objek `hukuman` milik overlayLive
    'rata' => 'kiri',
])

{{--
    Deret hukuman ringkas untuk overlay siaran.

    Bedanya dengan <x-silat.baris-hukuman>: komponen itu menerima jumlah terisi
    sebagai nilai PHP yang sudah tetap saat halaman digambar. Overlay tidak
    pernah dimuat ulang — ia menyala berjam-jam dan seluruh isinya berubah lewat
    Alpine — jadi jumlah kolom yang menyala di sini harus dibaca reaktif dari
    `hukuman[sisi]`, bukan disuntik sekali di server.

    Tidak ada teks label. Di scorebug ruangnya sempit dan namanya sudah dibawa
    overlay breakdown; yang dibutuhkan penonton di bar utama hanya "ada sanksi
    berjalan, sebanyak ini, seberat ini".
--}}

@php($kananDulu = $rata === 'kanan')

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 '.($kananDulu ? 'flex-row-reverse' : '')]) }}
     aria-hidden="true">
    @foreach ($kolom as $jenis => $gaya)
        <div class="flex gap-1 {{ $kananDulu ? 'flex-row-reverse' : '' }}">
            @for ($i = 1; $i <= $gaya['jumlah']; $i++)
                <span class="h-1.5 w-4 rounded-[2px]"
                      x-bind:class="(hukuman?.{{ $sisi }}?.{{ $jenis }} ?? 0) >= {{ $i }} ? '{{ $gaya['nyala'] }}' : 'bg-black/30'"></span>
            @endfor
        </div>
    @endforeach
</div>
