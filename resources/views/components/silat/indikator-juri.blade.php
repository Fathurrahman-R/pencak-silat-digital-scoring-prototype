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
    {{--
        Kotak bernomor juri, bukan bulatan bernomor di bawahnya.

        Nomornya dulu duduk sebagai keterangan di bawah titik, dan mata harus
        melompat naik-turun untuk memasangkan "yang menyala" dengan "juri
        keberapa". Di dalam kotak, keduanya satu benda: yang menyala sudah
        menyebutkan namanya sendiri.
    --}}
    {{-- Deret bersambung bertepi lurus, bukan kotak-kotak terpisah bersudut
         membulat: dibaca dari tepi matras, ia harus terbaca sebagai satu alat
         ukur, bukan tiga benda yang kebetulan berjajar. --}}
    <div class="flex justify-center border-[1.5px] border-silat-tepi-petak" aria-hidden="true">
        @for ($i = 1; $i <= $jumlah; $i++)
            <span @class([
                    'silat-angka grid h-11 w-[54px] shrink-0 place-items-center border-e-[1.5px] border-silat-tepi-petak text-[20px] font-semibold last:border-e-0',
                    $warnaNyala.' text-silat-teks' => in_array($i, $menekan, true),
                    'text-silat-teks-samar' => ! in_array($i, $menekan, true),
                ])>J{{ $i }}</span>
        @endfor
    </div>

    <p class="silat-angka text-[11px] text-silat-teks-samar">{{ $ambang }} dari {{ $jumlah }} juri</p>
</div>
