@props([
    'jenis',
    'sudut' => 'merah',
])

@php
    /*
     * Tombol nilai untuk panel juri.
     *
     * Ditekan sambil berdiri, di gelanggang yang berisik, sambil mata tetap
     * pada pesilat — bukan pada layar. Semua keputusan di sini berangkat dari
     * kenyataan itu:
     *
     *   - Target sentuh minimal 64px (--silat-sentuh-min). Bukan angka estetis;
     *     di bawah itu jari meleset saat pemakainya tidak melihat.
     *   - Ikon dan angka nilai berdampingan, tanpa mengandalkan teks. Juri
     *     mengenali bentuk lebih cepat daripada membaca kata.
     *   - Umpan balik tekan terjadi seketika lewat `active:`, tidak menunggu
     *     balasan server. Menunggu jaringan membuat juri ragu apakah tekanannya
     *     masuk, lalu menekan dua kali.
     *   - Saat koneksi putus, tombol benar-benar dinonaktifkan, bukan sekadar
     *     diberi tanda. Tombol yang bisa ditekan tapi nilainya tidak sampai
     *     jauh lebih berbahaya daripada tombol yang jelas-jelas mati.
     */
    $nilai = config("scoring.tanding.nilai.{$jenis}");

    $label = [
        'pukulan' => 'Pukulan',
        'tendangan' => 'Tendangan',
        'jatuhan' => 'Jatuhan',
    ][$jenis] ?? $jenis;

    $latar = $sudut === 'biru'
        ? 'bg-silat-biru active:bg-silat-biru-dalam'
        : 'bg-silat-merah active:bg-silat-merah-dalam';
@endphp

<button
    type="button"
    x-bind:disabled="! $store.koneksi.tersambung"
    {{ $attributes->merge([
        'class' => $latar.' '.implode(' ', [
            'flex w-full select-none flex-col items-center justify-center gap-1',
            'rounded-silat text-silat-teks',
            'min-h-[var(--silat-sentuh-min)] min-w-[var(--silat-sentuh-min)] px-4 py-3',
            'transition-none touch-manipulation',
            'active:translate-y-px',
            'disabled:bg-silat-mati disabled:text-silat-teks-samar',
            'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
        ]),
    ]) }}
    aria-label="{{ $label }}, nilai {{ $nilai }}, sudut {{ $sudut }}"
>
    <x-silat.ikon :nama="$jenis" :ukuran="34" :label="null" />

    <span class="flex items-baseline gap-1.5">
        <span class="text-[13px] tracking-wide">{{ $label }}</span>
        <span class="silat-angka text-[20px] font-medium">{{ $nilai }}</span>
    </span>
</button>
