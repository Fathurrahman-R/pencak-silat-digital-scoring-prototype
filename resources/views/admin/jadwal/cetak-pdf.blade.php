<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Partai</title>
    {{--
        Jadwal siap cetak, satu tabel per gelanggang.

        TANPA kolom jam. Jadwal menyimpan urutan tayang, bukan jam dinding:
        partai molor karena protes, verifikasi juri, dan cedera, sehingga jam
        yang tercetak pagi hari sudah meleset sebelum gelanggang kedua selesai
        babak pertama.

        Nama kelas ditulis lengkap, bukan "Kelas A" saja. Cetakan ini dibaca
        tanpa kolom lain di sekitarnya, dan Kelas A putra dan putri akan tampak
        sebagai dua baris yang sama persis.
    --}}
    <style>
        /* Latar dinyatakan, tidak diwariskan: berkas ini juga dibuka langsung
           di peramban saat diperiksa, dan peramban bertema gelap akan menelan
           teks #111 di atas latar yang tidak pernah diputihkan. */
        body { background: #fff; font-family: sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.sub { margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        .center { text-align: center; }
        .kontingen { color: #555; font-size: 10px; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 4px; }
        .kosong { color: #555; font-style: italic; }
    </style>
</head>
<body>
    <h1>Jadwal Partai</h1>
    <p class="sub">
        {{ $tournament->name }} — dicetak {{ now()->translatedFormat('d M Y, H:i') }}.
        Partai berjalan menurut nomor urut, bukan jam.
    </p>

    @forelse ($arenas as $arena)
        <p class="section-title">{{ $arena->name }} — {{ $arena->matches->count() }} partai</p>

        @if ($arena->matches->isEmpty())
            <p class="kosong">Belum ada partai yang ditempatkan di gelanggang ini.</p>
        @else
            <table>
                <tr>
                    <th class="center" style="width: 34px">No.</th>
                    <th style="width: 34px">Partai</th>
                    <th>Kelas</th>
                    <th>Babak</th>
                    <th>Sudut merah</th>
                    <th>Sudut biru</th>
                    <th style="width: 70px">Status</th>
                </tr>

                @foreach ($arena->matches as $partai)
                    <tr>
                        <td class="center">{{ $partai->order_in_arena }}</td>
                        <td>{{ $partai->id }}</td>
                        <td>{{ $partai->bracket->weightClass->namaLengkap() }}</td>
                        <td>{{ $partai->bracket->namaBabak($partai->round) }}</td>
                        <td>
                            {{ $partai->red?->athletes->pluck('name')->implode(', ') ?: '—' }}
                            @if ($partai->red)
                                <div class="kontingen">{{ $partai->red->contingent->name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $partai->blue?->athletes->pluck('name')->implode(', ') ?: '—' }}
                            @if ($partai->blue)
                                <div class="kontingen">{{ $partai->blue->contingent->name }}</div>
                            @endif
                        </td>
                        <td>{{ ucfirst($partai->status) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @empty
        <p class="kosong">Kejuaraan ini belum punya gelanggang aktif.</p>
    @endforelse
</body>
</html>
