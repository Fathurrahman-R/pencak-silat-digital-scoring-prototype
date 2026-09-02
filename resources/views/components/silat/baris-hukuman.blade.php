@props([
    'jenis',
    'terisi' => 0,
    'rata' => 'kiri',
    'pada' => 'panel',
    'ukuran' => 44,
])

@php
    /*
     * Petak hukuman: satu petak per sanksi yang mungkin dijatuhkan, menyala
     * sebanyak yang sudah jatuh.
     *
     * Jumlah petaknya dibaca dari config/scoring.php, bukan ditulis di sini,
     * supaya angkanya tetap satu sumber dengan mesin scoring dan tidak pernah
     * berbeda antara yang dihitung server dan yang dilihat penonton.
     *
     * Pasal 11.6.d.4:
     *   pembinaan  2 petak — tidak mengurangi nilai, tapi dua pembinaan adalah
     *              ambang yang menentukan: pelanggaran ringan berikutnya naik
     *              menjadi teguran
     *   teguran    2 petak — teguran ketiga tidak pernah muncul di sini, ia
     *              langsung menjadi Peringatan I
     *   peringatan 3 petak — petak ketiga bergaris putus karena ia bukan
     *              pengurangan nilai: mengisinya berarti diskualifikasi
     *
     * Jumlah dibaca dari BERAPA PETAK YANG MENYALA, tidak pernah dari angka.
     * Dari tepi matras, menghitung dua kotak lebih cepat daripada membaca "×2",
     * dan petak yang masih kosong sekaligus memberi tahu berapa langkah lagi
     * sebelum naik tingkat.
     */
    $jumlahPetak = config("scoring.tanding.hukuman.{$jenis}.jumlah_kolom", 0);

    /*
     * Tiap petak memuat ikon jenisnya, terisi maupun belum -- supaya petaknya
     * terbaca untuk apa bahkan sebelum ada hukuman. Yang membedakan terisi dari
     * kosong adalah bidang warnanya, bukan ada-tidaknya ikon.
     *
     * Petak kosong TIDAK PERNAH berupa bidang abu. Bidang gelap yang dipakai
     * sebelumnya (`bg-black/25`) berkontras 1.27 di atas blok sudut: petak yang
     * belum terisi praktis tidak terlihat. Sekarang ia berupa tepi yang terukur
     * 3.15 di bidang merah dan 4.01 di bidang biru.
     */
    $bidang = [
        'pembinaan' => 'bg-silat-pembinaan',
        'teguran' => 'bg-silat-teguran',
        'peringatan' => 'bg-silat-peringatan',
    ][$jenis] ?? 'bg-silat-teks-redup';

    // Tinta ikon mengikuti bidangnya, bukan sebaliknya: Peringatan kini bidang
    // putih pekat, jadi ikonnya gelap. BRIEF §2.3.
    $tintaIkon = [
        'pembinaan' => 'text-silat-pembinaan-teks',
        'teguran' => 'text-silat-teguran-teks',
        'peringatan' => 'text-silat-peringatan-teks',
    ][$jenis] ?? 'text-silat-teks';

    // Label mengikuti latar tempat petaknya duduk: di atas blok sudut ia harus
    // memakai nuansa sudut, bukan abu yang tenggelam.
    $tintaLabel = $pada === 'sudut'
        ? ($rata === 'kanan' ? 'text-silat-teks-biru-samar' : 'text-silat-teks-merah-samar')
        : 'text-silat-teks-redup';

    $label = ['pembinaan' => 'Pembinaan', 'teguran' => 'Teguran', 'peringatan' => 'Peringatan'][$jenis] ?? $jenis;

    $terisi = max(0, min((int) $terisi, $jumlahPetak));
    $ikonPx = (int) round($ukuran * 0.55);
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col gap-1.5 '.($rata === 'kanan' ? 'items-end' : 'items-start')]) }}
    role="group"
    aria-label="{{ $label }}: {{ $terisi }} dari {{ $jumlahPetak }}"
>
    {{-- Satu deret bersambung bertepi lurus. Kotak terpisah bersudut membulat
         terbaca sebagai benda-benda yang tak berhubungan; yang ini satu tangga,
         dan yang dicari mata dari tepi matras cuma sampai mana ia terisi. --}}
    <div class="flex border-[1.5px] border-silat-tepi-petak" aria-hidden="true">
        @for ($i = 1; $i <= $jumlahPetak; $i++)
            @php
                $nyala = $i <= $terisi;
                // Petak terakhir peringatan berarti diskualifikasi, bukan
                // pengurangan nilai -- dibedakan garis pemisah yang putus.
                $diskualifikasi = $jenis === 'peringatan' && $i === $jumlahPetak;
            @endphp
            <span @class([
                'flex shrink-0 items-center justify-center border-e-[1.5px] border-silat-tepi-petak last:border-e-0',
                $bidang => $nyala,
                'border-s-[1.5px] border-dashed' => $diskualifikasi,
            ]) style="width: {{ $ukuran }}px; height: {{ $ukuran }}px;">
                <x-silat.ikon-hukuman :jenis="$jenis" :tingkat="$i" :nyala="$nyala" :ukuran="$ikonPx" />
            </span>
        @endfor
    </div>

    <span class="text-[11px] tracking-[.06em] uppercase {{ $tintaLabel }}">{{ $label }}</span>
</div>
