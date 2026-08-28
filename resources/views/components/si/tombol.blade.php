@props([
    'varian' => 'utama',
    'ukuran' => 'sedang',
    'tautan' => null,
    'tipe' => 'submit',
    'ikon' => null,
    'penuh' => false,
    'nonaktif' => false,
])

@php
    /*
     * Tombol aksi TIDAK memakai warna.
     *
     * Merah, biru, dan emas semuanya sudah punya arti yang ditetapkan
     * peraturan pertandingan -- sudut pesilat dan juara. Tidak ada warna aman
     * yang tersisa untuk "aksi utama", jadi ia tidak berwarna sama sekali:
     * bidang tinta dengan teks putih di suasana terang, bidang terang dengan
     * teks gelap di suasana gelap. Kontrasnya 18.01, tertinggi di seluruh
     * sistem, dan mustahil tertukar dengan apa pun yang berarti sudut.
     *
     * Lihat docs/BRIEF-DESAIN.md §5.
     */
    $varianKelas = [
        'utama' => 'bg-accent text-accent-on font-semibold hover:opacity-90',
        'kedua' => 'border border-line-strong bg-accent-soft text-ink font-semibold hover:brightness-95',
        'polos' => 'text-ink-secondary hover:bg-surface-inset hover:text-ink',

        /*
         * Merah bahaya dan merah sudut memang serupa, dan yang memisahkannya
         * bukan rona melainkan peran bentuk: sudut selalu bidang besar
         * berlabel "Sudut Merah", bahaya selalu tombol kecil berkata kerja.
         * Di panel gelanggang tidak ada tombol bahaya berwarna sama sekali --
         * aksi merusak di sana memakai `utama` dan dilindungi konfirmasi.
         */
        'bahaya' => 'border border-danger text-danger font-semibold hover:bg-danger-soft',
        'bahaya-tegas' => 'bg-danger text-danger-on font-semibold hover:opacity-90',
    ];

    /*
     * Tinggi kontrol. `gelanggang` adalah 64px yang tidak bisa ditawar: tombol
     * di panel ditekan cepat sambil berdiri, tanpa sempat melihat lama.
     */
    $ukuranKelas = [
        'kecil' => 'h-8 gap-1.5 rounded-[var(--radius-kecil)] px-3 text-[13px]',
        'sedang' => 'h-[var(--sentuh-admin)] gap-2 rounded-[var(--radius)] px-[18px] text-[15px]',
        'besar' => 'h-12 gap-2 rounded-[var(--radius)] px-[22px] text-[16px]',
        'gelanggang' => 'h-[var(--sentuh-gelanggang)] gap-2.5 rounded-[var(--radius)] px-6 text-[17px]',
        'ikon' => 'size-[var(--sentuh-admin)] rounded-[var(--radius)] p-0',
    ];

    $kelas = implode(' ', [
        'inline-flex shrink-0 items-center justify-center whitespace-nowrap',
        'transition-[opacity,background-color,filter] duration-120 outline-none',

        // Fokus berupa cincin ganda: bidang latar di dalam, tinta di luar.
        // Pola ini terbaca di atas permukaan apa pun tanpa menambah warna,
        // dan tidak pernah dihilangkan.
        'focus-visible:ring-2 focus-visible:ring-surface focus-visible:ring-offset-2 focus-visible:ring-offset-ink',

        'aria-disabled:pointer-events-none aria-disabled:opacity-45',
        'disabled:pointer-events-none disabled:opacity-45',
        $varianKelas[$varian] ?? $varianKelas['utama'],
        $ukuranKelas[$ukuran] ?? $ukuranKelas['sedang'],
        $penuh ? 'w-full' : '',
    ]);
@endphp

@if ($tautan && ! $nonaktif)
    <a href="{{ $tautan }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($ikon)
            <x-si.ikon :nama="$ikon" class="size-[18px] shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $tipe }}"
            @disabled($nonaktif)
            {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($ikon)
            <x-si.ikon :nama="$ikon" class="size-[18px] shrink-0" />
        @endif
        {{ $slot }}
    </button>
@endif
