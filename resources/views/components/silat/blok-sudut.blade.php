@props([
    'sudut' => 'merah',
    'atlet' => null,
    'kontingen' => null,
    'nilai' => 0,
    'pembinaan' => 0,
    'teguran' => 0,
    'peringatan' => 0,
    'ukuran' => 'papan',
])

@php
    /*
     * Blok satu sudut pesilat: identitas, skor, dan deret hukumannya.
     *
     * Merah selalu di kiri dan biru selalu di kanan pada papan skor, mengikuti
     * kebiasaan yang sudah dipakai penonton dan aparat. Menukarnya membuat
     * orang salah baca justru pada detik-detik yang paling menentukan.
     *
     * Warna sudut ditetapkan peraturan sebagai identitas, bukan pilihan gaya,
     * jadi tidak diambil dari token semantik yang bisa berubah.
     */
    $kanan = $sudut === 'biru';

    $latar = $sudut === 'biru' ? 'bg-silat-biru' : 'bg-silat-merah';
    $redup = $sudut === 'biru' ? 'text-[#9ebbea]' : 'text-[#f5afb2]';
    $samar = $sudut === 'biru' ? 'text-[#cbdbf7]' : 'text-[#fbd9da]';

    $namaSudut = $sudut === 'biru' ? 'Sudut biru' : 'Sudut merah';
@endphp

<div {{ $attributes->merge(['class' => $latar.' flex flex-col justify-between p-4 '.($kanan ? 'text-right' : '')]) }}>
    <div>
        <p class="text-[11px] tracking-[.08em] {{ $samar }}">{{ $namaSudut }}</p>
        <p class="text-[17px] font-medium text-silat-teks">{{ $atlet ?? '—' }}</p>
        <p class="text-[13px] {{ $redup }}">{{ $kontingen ?? '—' }}</p>
    </div>

    <x-silat.angka-skor :nilai="$nilai" :ukuran="$ukuran" class="mt-1 block text-silat-teks" />

    <div class="mt-3 flex flex-col gap-1.5 {{ $kanan ? 'items-end' : 'items-start' }}">
        <x-silat.baris-hukuman jenis="pembinaan" :terisi="$pembinaan" :rata="$kanan ? 'kanan' : 'kiri'" pada="sudut" />
        <x-silat.baris-hukuman jenis="teguran" :terisi="$teguran" :rata="$kanan ? 'kanan' : 'kiri'" pada="sudut" />
        <x-silat.baris-hukuman jenis="peringatan" :terisi="$peringatan" :rata="$kanan ? 'kanan' : 'kiri'" pada="sudut" />
    </div>
</div>
