<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bagan {{ $weightClass->namaLengkap() }}</title>
    {{--
        Bagan siap cetak — gambarnya sama dengan halaman Bagan di layar.

        Koordinat slot dan garis datang dari App\Support\Bagan\PohonBagan, sama
        persis dengan yang digambar di layar. Dihitung ulang khusus untuk cetak,
        pohonnya akan menyimpang diam-diam dari yang dilihat panitia -- dan
        kontingen yang membaca cetakan di papan pengumuman akan menyiapkan lawan
        yang berbeda dari yang tampil di layar gelanggang.

        # Warnanya penuh, bukan batang tepi

        Versi sebelumnya menandai sudut dengan garis tepi kiri tebal demi
        menghemat tinta. Akibatnya cetakan dan layar jadi dua gambar berbeda:
        yang satu bidang merah-biru penuh, yang satu kotak putih bergaris. Orang
        yang memegang cetakan sambil melihat layar harus menerjemahkan sendiri
        antara keduanya, dan itu pekerjaan yang tidak ada gunanya.

        Merah kiri biru kanan sudah jadi arti tetap di papan skor, overlay, dan
        panel gelanggang; cetakan pun harus memakai arti yang sama. Angka
        warnanya diambil langsung dari token layar (#7a1418 / #0c2a63) supaya
        keduanya tidak bisa bergeser sendiri-sendiri.

        # Kenapa gayanya ditulis sebaris

        dompdf tidak menjalankan build aset, jadi kelas Tailwind tidak berarti
        apa-apa di sini. Ia juga tidak mengenal flexbox: tata letak di dalam
        slot memakai TABEL, satu-satunya primitif tata letak yang dompdf gambar
        dengan andal, bukan float yang meleset saat namanya panjang.
    --}}
    <style>
        @page { margin: 0; }

        /* Latar dinyatakan, tidak diwariskan: berkas ini juga dibuka langsung
           di peramban saat diperiksa, dan peramban bertema gelap akan
           menelan teks gelap di atas latar yang tidak pernah diputihkan. */
        body { margin: 0; background: #fff; font-family: sans-serif; color: #09090b; }

        /* TANPA padding. Anak yang diposisikan mutlak dihitung dari kotak
           padding, dan seluruh anak di sini sudah menambahkan margin sendiri
           pada koordinatnya -- padding di sini menambahkannya untuk kedua kali,
           dan lembar jadi lebih tinggi daripada kertasnya. Gejalanya halaman
           kedua yang nyaris kosong di tiap cetakan. */
        .lembar { position: relative; }

        .kepala { position: absolute; left: 0; top: 0; }
        .kepala h1 { margin: 0; font-size: 16px; letter-spacing: -0.01em; }
        .kepala p { margin: 4px 0 0; font-size: 10px; color: #71717a; }

        .judul-kolom {
            position: absolute; font-size: 9px; letter-spacing: .1em;
            text-transform: uppercase; color: #71717a;
        }

        /* Slot: bidang sudut penuh dengan sudut membulat, sama seperti layar. */
        .slot { position: absolute; overflow: hidden; border-radius: 8px; }
        .slot table { width: 100%; height: 100%; border-collapse: collapse; }
        .slot td { vertical-align: middle; padding: 0; }

        .merah { background-color: #7a1418; color: #ffffff; }
        .biru { background-color: #0c2a63; color: #ffffff; }

        /* Slot yang penghuninya belum pasti tetap netral: mewarnainya lebih
           dulu berarti menjanjikan sudut yang belum diputuskan. */
        .kosong { background-color: #f4f4f5; color: #3f3f46; border: 1px dashed #e4e4e7; }
        .menunggu { background-color: #f4f4f5; color: #3f3f46; border: 1px solid #e4e4e7; }

        /* Nomor undian. Angkanya dibungkus blok, bukan ditaruh telanjang di
           dalam sel: dompdf menaruh teks telanjang pada garis dasar sel dan
           angkanya melorot ke bawah, tidak sejajar nama di sebelahnya seperti
           di layar. Perataan tengah dipakai menggantikan padding kiri, yang
           tidak dihormati dompdf pada sel bertelebar tetap -- akibatnya
           angkanya menempel di tepi kotak dan setengah termakan lengkungan. */
        .sel-nomor { width: 30px; padding: 0; text-align: center; }
        .sel-nomor div { font-size: 11px; line-height: 1; }
        .sel-isi { padding-left: 8px; padding-right: 8px; }
        .sel-bye { width: 30px; padding-right: 10px; text-align: right; font-size: 9px; font-weight: bold; text-transform: uppercase; }

        .nama { font-size: 13px; font-weight: bold; line-height: 1.15; }
        .kontingen { font-size: 11px; line-height: 1.15; padding-top: 2px; }

        /* Nama kontingen pada bidang berwarna: putih penuh terlalu ramai
           dibaca sebaris di bawah nama, jadi ia diturunkan satu tingkat --
           sama seperti token text-corner-*-on di layar. */
        .merah .kontingen, .biru .kontingen { color: #e8e2e3; }
        .kosong .kontingen, .menunggu .kontingen { color: #71717a; }

        .garis-h { position: absolute; border-top: 1px solid #a1a1aa; }
        .garis-v { position: absolute; border-left: 1px solid #a1a1aa; }
    </style>
</head>
<body>
    <div class="lembar" style="width: {{ $pohon['lebar'] + $margin * 2 }}px; height: {{ $pohon['tinggi'] + $kepala + $margin * 2 }}px">
        <div class="kepala" style="left: {{ $margin }}px; top: {{ $margin }}px; width: {{ $pohon['lebar'] }}px">
            <h1>{{ $weightClass->namaLengkap() }}</h1>
            <p>
                {{ $tournament->name }} ·
                {{ $bracket->terkunci()
                    ? 'Dikunci '.$bracket->locked_at->translatedFormat('d M Y, H:i').' oleh '.($bracket->locker?->name ?? '—')
                    : 'MASIH DRAF — susunannya belum final' }}
                · Dicetak {{ now()->translatedFormat('d M Y, H:i') }}
            </p>
        </div>

        @foreach ($pohon['kolom'] as $kolom)
            <div class="judul-kolom" style="left: {{ $margin + $kolom['x'] }}px; top: {{ $margin + $kepala - 15 }}px">
                {{ $kolom['judul'] }}
            </div>

            @foreach ($kolom['slot'] as $slot)
                @php
                    $kelas = match (true) {
                        (bool) ($slot['kosong'] ?? false) => 'kosong',
                        (bool) ($slot['menunggu'] ?? false) => 'menunggu',
                        $slot['sudut'] === 'merah' => 'merah',
                        default => 'biru',
                    };
                    $hampa = in_array($kelas, ['kosong', 'menunggu'], true);
                @endphp

                <div class="slot {{ $kelas }}"
                     style="left: {{ $margin + $kolom['x'] }}px; top: {{ $margin + $kepala + $slot['y'] }}px; width: {{ $kolom['lebar'] }}px; height: {{ $pohon['slot_tinggi'] }}px">
                    <table>
                        <tr>
                            @if ($hampa)
                                <td class="sel-isi">
                                    <div class="kontingen">
                                        {{-- Tempat undian yang tidak kebagian peserta. Bukan bye: pasangannya
                                             pun kosong, jadi tidak ada partai yang lahir dari sini sama sekali. --}}
                                        {{ ($slot['kosong'] ?? false) ? 'Tempat kosong' : 'Menunggu babak sebelumnya' }}
                                    </div>
                                </td>
                            @else
                                @isset($slot['nomor'])
                                    <td class="sel-nomor"><div>{{ $slot['nomor'] }}</div></td>
                                @endisset

                                <td class="sel-isi">
                                    {{-- Nama di atas, kontingen di bawahnya. Sebaris, keduanya
                                         berebut lebar kolom yang sama dan sama-sama terpotong. --}}
                                    <div class="nama">{{ $slot['nama'] }}</div>

                                    @if (! empty($slot['kontingen']))
                                        <div class="kontingen">{{ $slot['kontingen'] }}</div>
                                    @endif
                                </td>

                                @if ($slot['bye'] ?? false)
                                    <td class="sel-bye">bye</td>
                                @endif
                            @endif
                        </tr>
                    </table>
                </div>
            @endforeach
        @endforeach

        {{-- Garis digambar setelah slot supaya ia tidak tertutup kotak. --}}
        @foreach ($pohon['garis'] as $g)
            @if ($g['jenis'] === 'h')
                <div class="garis-h" style="left: {{ $margin + $g['x'] }}px; top: {{ $margin + $kepala + $g['y'] }}px; width: {{ $g['panjang'] }}px"></div>
            @else
                <div class="garis-v" style="left: {{ $margin + $g['x'] }}px; top: {{ $margin + $kepala + $g['y'] }}px; height: {{ $g['panjang'] }}px"></div>
            @endif
        @endforeach
    </div>
</body>
</html>
