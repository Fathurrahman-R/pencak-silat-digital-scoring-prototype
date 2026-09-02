@props([
    'sudut' => 'red',
    'kunciSkor' => 'merah',

    /*
     * Ukuran angka skor mengikuti jarak baca, bukan selera. Kartu ringkas di
     * panel Dewan Wasit Juri dan Keberatan dibaca dari jarak meja; papan skor
     * yang mengisi layar peraga dibaca dari tepi gelanggang.
     */
    'ukuranAngka' => 'panel',
])

@php
    /*
     * Kartu ringkas sudut — mengikuti `panel-dewan-wasit-juri.dc.html`.
     *
     * Bidang penuh sudut, isi rata tengah, satu angka besar. Hukuman TIDAK
     * ikut di sini: kartu ini menjawab "siapa dan berapa", sementara rincian
     * hukuman punya tempatnya sendiri di riwayat, yang justru dibaca baris per
     * baris saat sebuah nilai disengketakan.
     */
    $biru = $sudut === 'blue';

    $bidang = $biru ? 'bg-silat-biru-dalam' : 'bg-silat-merah-dalam';
    $label = $biru ? 'text-silat-teks-biru-samar' : 'text-silat-teks-merah-samar';
    $kedua = $biru ? 'text-silat-teks-biru' : 'text-silat-teks-merah-redup';
    $namaSudut = $biru ? 'Sudut biru' : 'Sudut merah';

    $angkaKelas = [
        'papan' => 'text-[104px] leading-none',
        'panel' => 'text-[52px] leading-none',
    ][$ukuranAngka] ?? 'text-[52px] leading-none';
@endphp

<div {{ $attributes->merge(['class' => $bidang.' rounded-silat-besar p-4 text-center']) }}>
    <p class="silat-angka text-[10px] tracking-[.14em] {{ $label }} uppercase">{{ $namaSudut }}</p>

    <p class="mt-1.5 text-[15px] leading-[1.25] font-semibold text-silat-teks"
       x-text="(match.{{ $sudut }}?.athletes ?? []).join(', ') || '—'"></p>

    <p class="mt-0.5 text-[12.5px] {{ $kedua }}" x-text="match.{{ $sudut }}?.contingent ?? '—'"></p>

    <p class="silat-angka mt-2.5 font-medium text-silat-teks {{ $angkaKelas }}"
       x-text="skorTotal.{{ $kunciSkor }}"></p>
</div>
