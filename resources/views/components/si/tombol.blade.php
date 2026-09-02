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
     * Tombol aksi — DESIGN-SYSTEM.md §7.
     *
     * Aksi utama TIDAK berwarna: primary di sistem ini netral (bidang tinta,
     * teks terang), bukan biru korporat. Itu bukan sekadar selera — merah,
     * biru, dan emas sudah punya arti yang ditetapkan peraturan pertandingan,
     * jadi satu-satunya warna yang aman untuk "aksi terpenting" adalah tidak
     * berwarna sama sekali.
     *
     * Satu aksi utama per layar. Sisanya sekunder atau halus.
     */
    $varianKelas = [
        'utama' => 'bg-accent text-accent-on font-medium hover:bg-accent-hover',
        'kedua' => 'border border-line bg-surface-raised text-ink font-medium hover:bg-surface-inset',
        'polos' => 'text-ink-secondary font-medium hover:bg-surface-inset hover:text-ink',

        /*
         * Destruktif bertepi, bukan berbidang: bidang merah pekat disimpan
         * untuk dialog konfirmasi, tempat aksinya sudah dipastikan sekali.
         */
        'bahaya' => 'border border-danger-line bg-surface-raised text-danger font-medium hover:bg-danger-soft',
        'bahaya-tegas' => 'bg-danger text-danger-on font-medium hover:opacity-90',

        'tautan' => 'text-ink underline underline-offset-2 hover:text-ink-secondary',
    ];

    /*
     * Tinggi kontrol mengikuti §7: sm 32, md 36, lg 38, ikon 32/36/40.
     * `gelanggang` adalah 64px yang tidak bisa ditawar — tombol di panel
     * ditekan cepat sambil berdiri, tanpa sempat melihat lama.
     */
    $ukuranKelas = [
        'kecil' => 'h-8 gap-[7px] rounded-[var(--radius)] px-3 text-[13px]',
        'sedang' => 'h-9 gap-2 rounded-[var(--radius)] px-3.5 text-[13.5px]',
        'besar' => 'h-9.5 gap-2 rounded-[var(--radius)] px-4 text-[14px]',
        'gelanggang' => 'h-[var(--sentuh-gelanggang)] gap-2.5 rounded-[var(--radius)] px-6 text-[16px] font-semibold',
        'ikon-kecil' => 'size-8 rounded-[var(--radius)] p-0',
        'ikon' => 'size-9 rounded-[var(--radius)] p-0',
        'ikon-besar' => 'size-10 rounded-[var(--radius)] p-0',
    ];

    $kelas = implode(' ', [
        'inline-flex shrink-0 items-center justify-center whitespace-nowrap',
        'transition-[background-color,opacity] duration-120 outline-none',

        // Fokus keyboard §12: cincin 3px tinta 8% plus tepi tinta. Tidak pernah
        // dihilangkan, dan tidak pernah diganti cincin biru bawaan peramban.
        'focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:border-accent',

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
            <x-si.ikon :nama="$ikon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $tipe }}"
            @disabled($nonaktif)
            {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($ikon)
            <x-si.ikon :nama="$ikon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </button>
@endif
