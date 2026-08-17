<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Medali</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.sub { margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        .center { text-align: center; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 4px; }
    </style>
</head>
<body>
    <h1>Rekap Medali</h1>
    <p class="sub">{{ $tournament->name }} — dicetak {{ now()->translatedFormat('d M Y, H:i') }}</p>

    <p class="section-title">Peringkat Umum Kontingen</p>
    <table>
        <tr><th>Peringkat</th><th>Kontingen</th><th class="center">Emas</th><th class="center">Perak</th><th class="center">Perunggu</th></tr>
        @forelse ($peringkatUmum as $i => $baris)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $baris['kontingen'] }}</td>
                <td class="center">{{ $baris['emas'] }}</td>
                <td class="center">{{ $baris['perak'] }}</td>
                <td class="center">{{ $baris['perunggu'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="center">Belum ada medali yang disahkan.</td></tr>
        @endforelse
    </table>

    <p class="section-title">Juara Kelas Tanding</p>
    <table>
        <tr><th>Kelas</th><th>Emas</th><th>Perak</th><th>Perunggu</th></tr>
        @forelse ($tanding as $baris)
            <tr>
                <td>
                    {{ $baris['kelas']->jenis_kelamin->label() }} {{ $baris['kelas']->golongan_usia->label() }} — {{ $baris['kelas']->name }}
                </td>
                <td>{{ $baris['emas']->athletes->pluck('name')->implode(', ') }} ({{ $baris['emas']->contingent->name }})</td>
                <td>{{ $baris['perak']->athletes->pluck('name')->implode(', ') }} ({{ $baris['perak']->contingent->name }})</td>
                <td>
                    @foreach ($baris['perunggu'] as $r)
                        {{ $r->athletes->pluck('name')->implode(', ') }} ({{ $r->contingent->name }})@if (! $loop->last), @endif
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="center">Belum ada kelas yang selesai.</td></tr>
        @endforelse
    </table>

    <p class="section-title">Juara Nomor Jurus</p>
    <table>
        <tr><th>Nomor</th><th>Emas</th><th>Perak</th><th>Perunggu</th></tr>
        @forelse ($jurus as $baris)
            <tr>
                <td>{{ $baris['nomor']->nama() }}</td>
                <td>{{ $baris['emas']?->athletes->pluck('name')->implode(', ') }}</td>
                <td>{{ $baris['perak']?->athletes->pluck('name')->implode(', ') }}</td>
                <td>{{ $baris['perunggu']?->athletes->pluck('name')->implode(', ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="center">Belum ada nomor yang selesai.</td></tr>
        @endforelse
    </table>
</body>
</html>
