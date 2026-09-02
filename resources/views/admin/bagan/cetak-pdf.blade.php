<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bagan {{ $weightClass->namaLengkap() }}</title>
    {{--
        Bagan siap cetak.

        Koordinat slot dan garis datang dari App\Support\Bagan\PohonBagan, sama
        persis dengan yang digambar di layar. Dihitung ulang khusus untuk cetak,
        pohonnya akan menyimpang diam-diam dari yang dilihat panitia -- dan
        kontingen yang membaca cetakan di papan pengumuman akan menyiapkan lawan
        yang berbeda dari yang tampil di layar gelanggang.

        Gayanya ditulis sebaris di sini, bukan memakai kelas Tailwind: dompdf
        tidak menjalankan build aset. Ia juga tidak mengenal flexbox, jadi
        koordinat mutlak justru satu-satunya cara yang bisa ia gambar.

        Sudut ditandai garis tepi kiri tebal, bukan bidang warna penuh. Bidang
        penuh menghabiskan tinta satu halaman dan membuat nama sulit dibaca pada
        mesin cetak hitam-putih, yang justru yang tersedia di sekretariat.
    --}}
    <style>
        @page { margin: 0; }
        /* Latar dinyatakan, tidak diwariskan: berkas ini juga dibuka langsung
           di peramban saat diperiksa, dan peramban bertema gelap akan
           menelan teks #111 di atas latar yang tidak pernah diputihkan. */
        body { margin: 0; background: #fff; font-family: sans-serif; color: #111; }
        .lembar { position: relative; }
        .kepala { position: absolute; left: 0; top: 0; }
        .kepala h1 { margin: 0; font-size: 15px; }
        .kepala p { margin: 3px 0 0; font-size: 10px; color: #555; }
        .judul-kolom { position: absolute; font-size: 9px; letter-spacing: .08em; text-transform: uppercase; color: #666; }
        .slot { position: absolute; overflow: hidden; border: 1px solid #bbb; border-left-width: 4px; }
        .merah { border-left-color: #7a1418; }
        .biru { border-left-color: #0c2a63; }
        .hampa { border-style: dashed; border-left-color: #bbb; color: #888; }
        .nomor { font-size: 9px; color: #777; }
        .nama { font-size: 11px; font-weight: bold; }
        .kontingen { font-size: 9px; color: #555; }
        .garis-h { position: absolute; border-top: 1px solid #999; }
        .garis-v { position: absolute; border-left: 1px solid #999; }
    </style>
</head>
<body>
    <div class="lembar" style="width: {{ $pohon['lebar'] + $margin * 2 }}px; height: {{ $pohon['tinggi'] + $kepala + $margin * 2 }}px; padding: {{ $margin }}px">
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
            <div class="judul-kolom" style="left: {{ $margin + $kolom['x'] }}px; top: {{ $margin + $kepala - 14 }}px">
                {{ $kolom['judul'] }}
            </div>

            @foreach ($kolom['slot'] as $slot)
                @php
                    $hampa = ($slot['kosong'] ?? false) || ($slot['menunggu'] ?? false);
                    $kelas = $hampa ? 'hampa' : ($slot['sudut'] === 'merah' ? 'merah' : 'biru');
                @endphp

                <div class="slot {{ $kelas }}"
                     style="left: {{ $margin + $kolom['x'] }}px; top: {{ $margin + $kepala + $slot['y'] }}px; width: {{ $kolom['lebar'] }}px; height: {{ $pohon['slot_tinggi'] }}px; padding: 3px 6px">
                    @if ($hampa)
                        <span class="kontingen">
                            {{ ($slot['kosong'] ?? false) ? 'Tempat kosong' : 'Menunggu babak sebelumnya' }}
                        </span>
                    @else
                        <div>
                            @isset($slot['nomor'])
                                <span class="nomor">{{ $slot['nomor'] }}</span>
                            @endisset
                            <span class="nama">{{ $slot['nama'] }}</span>
                        </div>
                        <div class="kontingen">{{ $slot['kontingen'] }}</div>
                    @endif
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
