@props([
    'jenis',
    'terisi' => 0,
    'rata' => 'kiri',
    'pada' => 'panel',
])

@php
    /*
     * Deret kolom hukuman: kolom menyala sebanyak sanksi yang sudah dijatuhkan.
     *
     * Jumlah kolomnya dibaca dari config/scoring.php, bukan ditulis di sini,
     * supaya angkanya tetap satu sumber dengan mesin scoring dan tidak pernah
     * berbeda antara yang dihitung server dan yang dilihat penonton.
     *
     * Pasal 11.6.d.4:
     *   pembinaan  2 kolom — tidak mengurangi nilai, tapi dua pembinaan adalah
     *              ambang yang menentukan: pelanggaran ringan berikutnya naik
     *              menjadi teguran
     *   teguran    2 kolom — teguran ketiga tidak pernah muncul di sini, ia
     *              langsung menjadi Peringatan I
     *   peringatan 3 kolom — kolom ketiga menyala berarti diskualifikasi
     *
     * Pembinaan sengaja dibuat paling redup. Ia tidak mengurangi nilai, dan
     * kalau tampil sekuat teguran, penonton akan membacanya sebagai sanksi
     * padahal dampaknya nol.
     */
    $jumlahKolom = config("scoring.tanding.hukuman.{$jenis}.jumlah_kolom", 0);

    /*
     * Dua konteks, dua skala warna.
     *
     * Di panel netral, tingkat keparahan diberi warna semantik: abu, oranye,
     * merah.
     *
     * Di atas blok sudut, warna itu justru runtuh — oranye di atas merah jadi
     * lumpur, dan merah di atas merah hilang sama sekali. Jadi di sana
     * keparahan dibawa oleh terang-gelap: makin berat makin putih. Bentuk
     * ikonnya tetap membedakan ketiganya, sehingga tidak ada informasi yang
     * hanya bergantung pada warna.
     */
    $gaya = $pada === 'sudut'
        ? [
            'pembinaan' => ['nyala' => 'bg-white/45', 'mati' => 'bg-black/25', 'teks' => 'text-white/60'],
            'teguran' => ['nyala' => 'bg-white/75', 'mati' => 'bg-black/25', 'teks' => 'text-white/75'],
            'peringatan' => ['nyala' => 'bg-white', 'mati' => 'bg-black/25', 'teks' => 'text-white/90'],
        ][$jenis] ?? ['nyala' => 'bg-white', 'mati' => 'bg-black/25', 'teks' => 'text-white/75']
        : [
            'pembinaan' => ['nyala' => 'bg-silat-pembinaan', 'mati' => 'bg-silat-mati', 'teks' => 'text-silat-teks-samar'],
            'teguran' => ['nyala' => 'bg-silat-teguran', 'mati' => 'bg-silat-mati', 'teks' => 'text-silat-teks-redup'],
            'peringatan' => ['nyala' => 'bg-silat-peringatan', 'mati' => 'bg-silat-mati', 'teks' => 'text-silat-teks-redup'],
        ][$jenis] ?? ['nyala' => 'bg-silat-teks-redup', 'mati' => 'bg-silat-mati', 'teks' => 'text-silat-teks-redup'];

    $label = ['pembinaan' => 'Pembinaan', 'teguran' => 'Teguran', 'peringatan' => 'Peringatan'][$jenis] ?? $jenis;

    $terisi = max(0, min((int) $terisi, $jumlahKolom));
    $kananDulu = $rata === 'kanan';
@endphp

<div
    {{ $attributes->merge(['class' => 'flex items-center gap-2 '.($kananDulu ? 'flex-row-reverse' : '')]) }}
    role="group"
    aria-label="{{ $label }}: {{ $terisi }} dari {{ $jumlahKolom }}"
>
    <x-silat.ikon :nama="$jenis" :ukuran="14" :label="null" class="{{ $gaya['teks'] }}" />

    <span class="text-[11px] tracking-wide {{ $gaya['teks'] }}">{{ $label }}</span>

    <div class="flex gap-1 {{ $kananDulu ? 'flex-row-reverse' : '' }}" aria-hidden="true">
        @for ($i = 1; $i <= $jumlahKolom; $i++)
            <span class="h-2.5 w-5 rounded-[2px] {{ $i <= $terisi ? $gaya['nyala'] : $gaya['mati'] }}"></span>
        @endfor
    </div>
</div>
