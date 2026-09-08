@props([
    'sudut' => 'red',
    'kunciSkor' => 'merah',
])

@php
    /*
     * Blok sudut untuk panel operator.
     *
     * Susunannya MERAH KIRI, BIRU KANAN. Sebelumnya kedua blok bertumpuk, dan
     * perbandingan skornya menuntut mata bergerak turun sejauh setengah layar
     * -- jarak yang membuat operator kehilangan angka yang barusan dibacanya.
     * Berdampingan, kedua angka besar berdiri pada garis mata yang sama dan
     * selisihnya terbaca sekali pandang. Panel juri, wasit, live publik, dan
     * overlay memang sudah kiri-kanan, jadi susunan ini sekaligus menyamakan
     * arti "kiri" di semua layar gelanggang.
     *
     * Karena tiap blok kini cuma selebar setengah kolom, ukuran huruf tidak
     * lagi boleh diukur terhadap LEBAR LAYAR: 10vw di layar 1920 berarti
     * 192px, dan itu lebih lebar daripada blok yang memuatnya. Akar blok
     * dijadikan wadah pengukur (@container) dan seluruh clamp memakai cqw --
     * persen terhadap lebar blok sendiri, bukan lebar jendela. Pola yang sama
     * dipakai papan-hasil.
     *
     * Batas BAWAH tiap clamp dijaga tetap kecil karena di ponsel kedua blok
     * kembali bertumpuk dan tingginya cuma sepertiga layar.
     *
     * Bidang penuh sudut (#7a1418 / #0c2a63) dengan batang tepi warna sudut
     * terang di sisi kiri. Petak hukuman dan titik juri yang belum menyala
     * digambar sebagai TEPI, tidak pernah sebagai bidang abu.
     */
    $biru = $sudut === 'blue';

    $bidang = $biru ? 'bg-silat-biru-dalam' : 'bg-silat-merah-dalam';
    $rail = $biru ? 'bg-silat-biru' : 'bg-silat-merah';
    $tepi = $biru ? 'border-silat-teks-biru-samar' : 'border-silat-teks-merah-samar';
    $redup = $biru ? 'text-silat-teks-biru-samar' : 'text-silat-teks-merah-samar';
    $kedua = $biru ? 'text-silat-teks-biru' : 'text-silat-teks-merah-redup';

    /*
     * Petak hukuman duduk di atas batang sudut TERANG (#d42027 / #12439e),
     * bukan di bidang dalam yang gelap. Token tepi yang dipakai di seluruh
     * panel -- #8a8a90 -- dihitung terhadap bidang gelap: di atas merah terang
     * ia cuma 1,53, jauh di bawah ambang 3,0 untuk unsur non-teks, dan
     * deretnya praktis tidak terlihat. Token teks sudut yang terang lolos:
     * 3,54 di merah dan 5,80 di biru. Pasangan ini dijaga scripts/kontras.mjs.
     */
    $tepiRail = $biru ? 'border-silat-teks-biru' : 'border-silat-teks-merah-redup';
    $redupRail = $biru ? 'text-silat-teks-biru' : 'text-silat-teks-merah-redup';

    /*
     * Dua teknik saja. Jatuhan tidak lagi ditekan juri -- nilainya mutlak dan
     * diterbitkan wasit -- jadi barisnya tidak akan pernah menyala. Indikator
     * yang selamanya kosong terbaca operator sebagai juri yang tidak menekan,
     * bukan sebagai teknik yang memang bukan urusan juri.
     */
    $teknik = ['pukulan' => 'Pukulan', 'tendangan' => 'Tendangan'];
@endphp

<div {{ $attributes->class([
    '@container flex min-h-0 min-w-0 flex-1 items-stretch',
    $bidang,
    /*
     * Sudut biru dicerminkan: batang hukumannya berdiri di tepi KANAN.
     *
     * Kolom kendali duduk di antara kedua sudut, jadi rail yang tetap di kiri
     * membuat kedua deret hukuman berkumpul di tengah layar -- bertetangga
     * dengan kolom kendali dan berjauhan dari pesilat yang diwakilinya. Dengan
     * pencerminan ini tiap deret jatuh di tepi luar layar, sisi yang sama
     * dengan sudut matrasnya, dan jarak antara nama pesilat dan hukumannya
     * sama di kedua sisi.
     */
    'flex-row-reverse' => $biru,
]) }}>
    {{--
        Batang warna sudut sekaligus wadah indikator hukuman.

        Sebelumnya deret hukuman tersisip di bawah nama pesilat, sebaris dengan
        indikator juri -- dua alat ukur berbentuk sama persis, bertumpuk di
        kolom yang sama. Wasit yang mencari "sudah teguran berapa" harus
        memindai deret juri lebih dulu setiap kali. Dipindah ke batang sudut,
        tiap pertanyaan punya tempatnya sendiri, dan hukuman jatuh di tepi LUAR
        bidang berwarna -- kiri untuk merah, kanan untuk biru.

        Ketiga kelompoknya BERTUMPUK, tiap kelompok berderet mendatar. Jarak
        antar kelompok lebih lebar daripada jarak antar petak: itu satu-satunya
        yang memisahkan Pembinaan, Teguran, dan Peringatan sekarang, karena
        deretnya tidak lagi punya label.
    --}}
    {{--
        Lebar batang ditetapkan di sini, dan ketiga deret mengisinya penuh.

        Sebelumnya lebar batang lahir dari deret TERPANJANG -- Peringatan yang
        tiga petak -- sementara Pembinaan dan Teguran yang dua petak berhenti
        lebih pendek dan menyisakan pias warna di kanannya. Dari tepi matras
        ketiganya terbaca sebagai deret yang panjangnya berbeda-beda, padahal
        yang berbeda cuma jumlah petaknya. Dengan `flex-1` tiap petak membagi
        lebar batang rata, dan tingginya mengikuti isinya lewat padding.
    --}}
    <div class="{{ $rail }} flex w-[clamp(104px,23cqw,212px)] shrink-0 flex-col items-stretch justify-center gap-[clamp(8px,2.2cqw,26px)] px-[clamp(4px,1cqw,12px)] py-[clamp(8px,2.2cqw,26px)]">
        @foreach (['pembinaan', 'teguran', 'peringatan'] as $jenis)
            @php($jatah = config("scoring.tanding.hukuman.{$jenis}.jumlah_kolom", 0))

            <div class="flex w-full flex-row {{ $tepiRail }}" aria-hidden="true">
                @for ($i = 1; $i <= $jatah; $i++)
                    {{-- Petak terakhir Peringatan berarti diskualifikasi.
                         Penandanya tepi ATAS yang putus-putus. --}}
                    <span class="grid flex-1 place-items-center border-[1.5px] py-[clamp(5px,1.4cqw,14px)] {{ $tepiRail }}"
                          @class(['border-t-[1.5px] border-dashed' => $jenis === 'peringatan' && $i === $jatah])
                          x-bind:class="(hukuman?.{{ $kunciSkor }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                              ? 'bg-silat-teks text-silat-panel'
                              : '{{ $redupRail }}'">
                        <x-silat.ikon-hukuman :jenis="$jenis" :tingkat="$i" :nyala="true" :ukuran="34"
                                              x-bind:class="(hukuman?.{{ $kunciSkor }}?.{{ $jenis }} ?? 0) >= {{ $i }}
                                                  ? ''
                                                  : 'invert opacity-70'" />
                    </span>
                @endfor
            </div>
        @endforeach
    </div>

    {{--
        Isi blok, bertumpuk dari atas ke bawah: nama, angka, indikator juri.

        Angkanya duduk di tengah tinggi blok, jadi angka merah dan angka biru
        berdiri persis sejajar -- itulah seluruh gunanya susunan berdampingan.
    --}}
    <div class="flex min-w-0 flex-1 flex-col justify-between gap-[clamp(8px,1.8cqw,24px)] px-[clamp(12px,3.6cqw,44px)] py-[clamp(12px,3cqw,40px)]">
        <div class="min-w-0 text-center">
            <p class="text-[clamp(20px,7.4cqw,64px)] leading-[1.08] font-semibold tracking-[-0.025em] break-words text-silat-teks"
               x-text="(match.{{ $sudut }}?.athletes ?? []).join(', ') || '—'"></p>
            <p class="mt-[clamp(3px,0.8cqw,10px)] truncate text-[clamp(12px,3cqw,28px)] {{ $kedua }}"
               x-text="match.{{ $sudut }}?.contingent ?? '—'"></p>
        </div>

        <div class="flex min-h-0 flex-1 flex-col items-center justify-center">
            <p class="silat-angka text-center text-[clamp(56px,28cqw,260px)] leading-[0.8] font-medium tracking-[-0.04em] text-silat-teks"
               x-text="skorTotal.{{ $kunciSkor }}"></p>
            <span class="mt-[clamp(2px,1cqw,12px)] text-[clamp(13px,2.8cqw,30px)] {{ $kedua }}"
                  x-text="selisih.{{ $kunciSkor }}"></span>
        </div>

        {{-- Indikator juri per teknik: yang terlihat operator adalah berapa
             juri yang menekan teknik yang sama, karena itulah yang menentukan
             sebuah nilai sah atau tidak.

             Kotak bernomor juri, bukan bulatan polos. Operator yang melihat
             "dua bulatan menyala" masih harus menghitung sendiri bulatan
             keberapa yang menyala untuk tahu juri mana yang belum menekan --
             dan itu justru yang ditanyakan wasit saat nilai tidak terbit.
             J1/J2/J3 menjawabnya tanpa dihitung.

             Satu deret bersambung, bertepi lurus: dibaca dari tepi matras,
             tiga kotak terpisah bersudut membulat terbaca sebagai tiga benda
             yang tak berhubungan.

             SELEBAR blok, bukan selebar isinya. Petak bernomor tetap adalah
             satu-satunya unsur di blok ini yang lebarnya tidak ikut lebar
             kolom, dan pada layar gelanggang ia menyusut jadi pita kecil di
             tengah bidang berwarna -- justru unsur yang dicari mata saat nilai
             tidak kunjung terbit. Dengan `flex-1` tiap petak membagi lebar
             blok rata, dan tingginya mengikuti isinya lewat padding, bukan
             angka tetap yang harus dijaga cocok dengan lebarnya. --}}
        <div class="flex w-full shrink-0 flex-col" x-show="peraturan.jumlah_juri">
            @foreach ($teknik as $kunci => $label)
                <div class="flex w-full border-[1.5px] {{ $tepi }}" aria-hidden="true">
                    <template x-for="i in peraturan.jumlah_juri" :key="i">
                        <span class="silat-angka grid flex-1 place-items-center border-e-[1.5px] py-[clamp(6px,1.7cqw,17px)] text-[clamp(15px,3cqw,30px)] font-semibold last:border-e-0 {{ $tepi }} {{ $redup }}"
                              x-bind:class="(indikatorTeknik?.{{ $sudut }}?.{{ $kunci }} ?? []).includes(i)
                                  ? 'bg-silat-teks text-silat-panel'
                                  : ''"
                              x-text="'J' + i"></span>
                    </template>
                </div>
            @endforeach
        </div>
    </div>
</div>
