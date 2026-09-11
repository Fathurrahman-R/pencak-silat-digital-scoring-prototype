<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Jurus</title>
    {{--
        Jadwal Jurus siap cetak, satu tabel per gelanggang.

        Berkas terpisah dari jadwal Tanding, bukan satu tabel bercampur: nomor
        urut kedua kategori berdiri sendiri di kolom tabel yang berbeda, dan
        satu daftar gabungan akan menjanjikan urutan yang tidak pernah ada.

        TANPA kolom jam, alasan yang sama dengan jadwal Tanding.

        Sudut ditulis sebagai KATA, bukan bidang warna. Sebagian besar panitia
        meja gelanggang mencetak hitam-putih, dan bidang merah tua dan biru tua
        yang dicetak abu-abu tidak bisa dibedakan satu sama lain.
    --}}
    <style>
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
    <h1>Jadwal Jurus</h1>
    <p class="sub">
        {{ $tournament->name }} — dicetak {{ now()->translatedFormat('d M Y, H:i') }}.
        Penampilan berjalan menurut nomor urut, bukan jam. Pada nomor berformat battle,
        sudut biru tampil lebih dulu.
    </p>

    @forelse ($arenas as $arena)
        <p class="section-title">{{ $arena->name }} — {{ $arena->jurusPerformances->count() }} penampilan</p>

        @if ($arena->jurusPerformances->isEmpty())
            <p class="kosong">Belum ada penampilan Jurus yang ditempatkan di gelanggang ini.</p>
        @else
            <table>
                <tr>
                    <th class="center" style="width: 34px">No.</th>
                    <th>Nomor</th>
                    <th style="width: 74px">Tahap</th>
                    <th style="width: 52px">Sudut</th>
                    <th>Peserta</th>
                    <th style="width: 70px">Status</th>
                </tr>

                @foreach ($arena->jurusPerformances as $penampilan)
                    <tr>
                        <td class="center">{{ $penampilan->order_in_arena }}</td>
                        <td>{{ $penampilan->jurusEvent->nama() }}</td>
                        <td>{{ \App\Support\Bagan\TahapBaganJurus::label($penampilan->tahap) }}</td>
                        <td>{{ $penampilan->sudut ? ucfirst($penampilan->sudut) : '—' }}</td>
                        <td>
                            {{ $penampilan->registration?->athletes->pluck('name')->implode(', ') ?: '—' }}
                            @if ($penampilan->registration)
                                <div class="kontingen">{{ $penampilan->registration->contingent->name }}</div>
                            @endif
                        </td>
                        <td>{{ ucfirst($penampilan->status) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @empty
        <p class="kosong">Kejuaraan ini belum punya gelanggang aktif.</p>
    @endforelse
</body>
</html>
