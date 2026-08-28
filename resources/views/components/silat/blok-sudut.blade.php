@props([
    'sudut' => 'merah',
    'atlet' => null,
    'kontingen' => null,
    'nilai' => 0,
    'pembinaan' => 0,
    'teguran' => 0,
    'peringatan' => 0,
    'ukuran' => 'papan',

    // Sisi petak hukuman. 32px cukup terbaca dari tepi matras tanpa membuat
    // tujuh petak memakan lebar blok sudut.
    'ukuranPetak' => 32,
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

    /*
     * Nuansa teks kedua sudut TIDAK simetris, dan itu terpaksa: merah (#d42027)
     * jauh lebih terang daripada biru (#12439e), jadi headroom kontrasnya lebih
     * sempit. Nuansa merah muda yang dulu dipakai di sisi merah hanya mencapai
     * 2.89:1 — gagal AA — sementara padanannya di sisi biru lolos 4.63:1.
     * Akibatnya identitas pesilat merah selalu lebih sulit dibaca daripada biru,
     * padahal peraturan menempatkan keduanya setara.
     *
     * Angka di bawah dihitung terhadap latar sudutnya masing-masing, bukan
     * dikira-kira, dan semuanya >= 4.5:1. Kalau nuansa ini diubah, hitung ulang
     * — jangan disamakan begitu saja demi keseragaman rupa.
     */
    $redup = $sudut === 'biru' ? 'text-[#9ebbea]' : 'text-[#fff0f0]';   // 4.63 : 4.70
    $samar = $sudut === 'biru' ? 'text-[#cbdbf7]' : 'text-[#fff5f5]';   // 6.47 : 4.86

    $namaSudut = $sudut === 'biru' ? 'Sudut biru' : 'Sudut merah';
@endphp

<div {{ $attributes->merge(['class' => $latar.' flex flex-col justify-between p-4 '.($kanan ? 'text-right' : '')]) }}>
    <div>
        <p class="text-[11px] tracking-[.08em] {{ $samar }}">{{ $namaSudut }}</p>
        <p class="text-[17px] font-medium text-silat-teks">{{ $atlet ?? '—' }}</p>
        <p class="text-[13px] {{ $redup }}">{{ $kontingen ?? '—' }}</p>
    </div>

    <x-silat.angka-skor :nilai="$nilai" :ukuran="$ukuran" class="mt-1 block text-silat-teks" />

    {{--
        Tiga kelompok berdampingan, bukan bertumpuk: posisinya jadi tetap, dan
        dari tepi matras mata cukup menghafal tempat alih-alih membaca. Urutan
        Pembinaan-Teguran-Peringatan SAMA di kedua sudut walau bloknya
        bercermin -- kalau urutannya ikut dibalik, mata harus membaca dua arah
        berbeda untuk membandingkan kedua pesilat.
    --}}
    <div class="mt-3 flex gap-4 {{ $kanan ? 'justify-end' : 'justify-start' }}">
        <x-silat.baris-hukuman jenis="pembinaan" :terisi="$pembinaan" pada="sudut"
                               :rata="$kanan ? 'kanan' : 'kiri'" :ukuran="$ukuranPetak" />
        <x-silat.baris-hukuman jenis="teguran" :terisi="$teguran" pada="sudut"
                               :rata="$kanan ? 'kanan' : 'kiri'" :ukuran="$ukuranPetak" />
        <x-silat.baris-hukuman jenis="peringatan" :terisi="$peringatan" pada="sudut"
                               :rata="$kanan ? 'kanan' : 'kiri'" :ukuran="$ukuranPetak" />
    </div>
</div>
