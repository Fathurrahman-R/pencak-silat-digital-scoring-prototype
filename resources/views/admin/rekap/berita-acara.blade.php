<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Partai</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.sub { margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        .center { text-align: center; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 4px; }
        .tanda-tangan { width: 32%; display: inline-block; margin-top: 40px; }
        .garis { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Berita Acara Partai</h1>
    <p class="sub">
        {{ $match->bracket->weightClass->tournament->name }} —
        {{ $match->bracket->weightClass->jenis_kelamin->label() }}
        {{ $match->bracket->weightClass->golongan_usia->label() }}
        {{ $match->bracket->weightClass->name }} — {{ $match->bracket->namaBabak($match->round) }}
    </p>

    <table>
        <tr>
            <th>Sudut</th>
            <th>Atlet</th>
            <th>Kontingen</th>
            <th>Skor Total</th>
        </tr>
        <tr>
            <td>Merah</td>
            <td>{{ $match->red?->athletes->pluck('name')->implode(', ') }}</td>
            <td>{{ $match->red?->contingent->name }}</td>
            <td class="center">{{ $skorTotal['merah'] }}</td>
        </tr>
        <tr>
            <td>Biru</td>
            <td>{{ $match->blue?->athletes->pluck('name')->implode(', ') }}</td>
            <td>{{ $match->blue?->contingent->name }}</td>
            <td class="center">{{ $skorTotal['biru'] }}</td>
        </tr>
    </table>

    <p>
        <strong>Status:</strong> {{ $match->disahkan() ? 'Sah — disahkan '.$match->ratified_at->translatedFormat('d M Y, H:i') : 'Belum disahkan' }}<br>
        @if ($match->win_reason)
            <strong>Hasil:</strong> Menang {{ $match->win_reason }}
        @endif
    </p>

    <p class="section-title">Skor per Babak</p>
    <table>
        <tr><th>Babak</th><th>Merah</th><th>Biru</th></tr>
        @foreach ($rounds as $r)
            <tr>
                <td class="center">{{ $r['round'] }}</td>
                <td class="center">{{ $r['skor_merah'] }}</td>
                <td class="center">{{ $r['skor_biru'] }}</td>
            </tr>
        @endforeach
    </table>

    <p class="section-title">Daftar Nilai</p>
    <table>
        <tr><th>Babak</th><th>Sudut</th><th>Jenis</th><th>Nilai</th><th>Waktu</th></tr>
        @forelse ($nilai as $n)
            <tr>
                <td class="center">{{ $n->round }}</td>
                <td>{{ $n->corner === \App\Enums\Sudut::Merah ? 'Merah' : 'Biru' }}</td>
                <td>{{ $n->point_type->label() }}</td>
                <td class="center">{{ $n->value }}</td>
                <td>{{ $n->server_ts->format('H:i:s') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="center">Tidak ada nilai tercatat.</td></tr>
        @endforelse
    </table>

    <p class="section-title">Daftar Hukuman</p>
    <table>
        <tr><th>Babak</th><th>Sudut</th><th>Tahap</th><th>Level</th><th>Pengurangan</th><th>Tingkat Pelanggaran</th></tr>
        @forelse ($hukuman as $h)
            <tr>
                <td class="center">{{ $h->round }}</td>
                <td>{{ $h->corner === \App\Enums\Sudut::Merah ? 'Merah' : 'Biru' }}</td>
                <td>{{ $h->tier->label() }}</td>
                <td class="center">{{ $h->level }}</td>
                <td class="center">{{ $h->points ?? '(DQ)' }}</td>
                <td>{{ $h->violation_level?->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center">Tidak ada hukuman tercatat.</td></tr>
        @endforelse
    </table>

    <p class="section-title">Pengesahan</p>
    <table>
        <tr>
            <td class="garis center">Wasit</td>
            <td class="garis center">Ketua Pertandingan</td>
            <td class="garis center">Dewan Wasit Juri</td>
        </tr>
    </table>
</body>
</html>
