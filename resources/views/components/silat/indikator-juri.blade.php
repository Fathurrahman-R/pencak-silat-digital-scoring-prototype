@props([
    'jumlah' => null,
    'menekan' => [],
    'sudut' => null,
    'ambang' => null,
])

@php
    /*
     * Titik per juri, menyala saat juri itu menekan tombol.
     *
     * Ini bukan hiasan: inilah satu-satunya bagian antarmuka yang memperlihatkan
     * mengapa sebuah nilai terbit atau tidak terbit. Saat pelatih protes, yang
     * ditunjuk adalah baris ini — berapa juri yang sepakat, dan apakah
     * jumlahnya mencapai ambang.
     *
     * Jumlah juri dan ambang sepakat dibaca dari config/scoring.php. Naskah
     * 2025 Pasal 16 menetapkan 3 juri untuk kategori Tanding, tetapi tidak
     * mengatur berapa yang harus sepakat — itu keputusan implementasi, dan
     * karena itu tetap bisa diatur per turnamen.
     */
    $jumlah ??= config('scoring.juri.tanding.jumlah', 3);
    $ambang ??= config('scoring.juri.tanding.ambang_sepakat', 2);

    $menekan = array_map('intval', (array) $menekan);

    $warnaNyala = match ($sudut) {
        'merah' => 'bg-silat-merah',
        'biru' => 'bg-silat-biru',
        default => 'bg-silat-teks',
    };
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col items-center gap-1.5']) }}
    role="group"
    aria-label="Juri yang menekan: {{ count($menekan) }} dari {{ $jumlah }}, ambang sepakat {{ $ambang }}"
>
    <div class="flex justify-center gap-3.5">
        @for ($i = 1; $i <= $jumlah; $i++)
            <div class="text-center">
                <span
                    class="block size-[22px] rounded-full {{ in_array($i, $menekan, true) ? $warnaNyala : 'bg-silat-mati' }}"
                    aria-hidden="true"
                ></span>
                <span class="silat-angka mt-1 block text-[11px] text-silat-teks-samar">J{{ $i }}</span>
            </div>
        @endfor
    </div>

    <p class="silat-angka text-[11px] text-silat-teks-samar">{{ $ambang }} dari {{ $jumlah }} juri</p>
</div>
